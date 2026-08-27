/* ============================================================
   Furusato Japanese Restaurant - Menu Page JavaScript
   Handles: menu data fetching, rendering, search, category nav,
   scroll reveal, specials highlighting, WhatsApp ordering
   FIXED: Specials API disabled to prevent price override issues
   ============================================================ */

(function () {
  "use strict";

  /* ----------------------------------------------------------
     Configuration
     ---------------------------------------------------------- */
  var API_MENU = "api/menu.php";
  var API_SPECIALS = "api/specials.php";
  var SCROLL_OFFSET = 80;
  var STAGGER_DELAY = 80;
  var SKELETON_COUNT = 6;

  /* ----------------------------------------------------------
     State
     ---------------------------------------------------------- */
  var menuData = null;
  var specialsData = null;
  var specialItemIds = {};
  var settingsData = null;
  var activeCategory = null;
  var searchQuery = "";

  /* ----------------------------------------------------------
     DOM References (populated on init)
     ---------------------------------------------------------- */
  var navInnerEl = null;
  var searchInputEl = null;
  var searchClearEl = null;
  var contentEl = null;
  var downloadEl = null;

  /* ----------------------------------------------------------
     SVG Icons
     ---------------------------------------------------------- */
  var SVG_WHATSAPP =
    '<svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.473-.148-.673.149-.2.297-.768.967-.94 1.164-.173.199-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.864 9.864 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
  var SVG_ARROW_UP =
    '<svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
  var SVG_DOWNLOAD =
    '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';

  /* ----------------------------------------------------------
     Cache Busting Helper - Add timestamp to image URLs
     ---------------------------------------------------------- */
  function addImageCacheBust(url) {
    // Return a STABLE url. The server already provides the correct
    // /v=<filemtime> via the `image_url` field, and the browser/service worker
    // cache on it. Never append a fresh Date.now() here: doing so gives every
    // image a brand-new URL on each render, which defeats caching and makes
    // menu images re-download (and flicker in/out) on every page load.
    if (!url) return url;
    if (url.startsWith('data:')) return url;
    if (url.startsWith('http') && !url.includes(window.location.hostname)) return url;
    var clean = url.split('?')[0];
    if (clean.indexOf('://') === -1 && clean.charAt(0) !== '/') {
      return '/' + clean;
    }
    return clean;
  }

  /* ----------------------------------------------------------
     Process menu data to add cache busting to images
     ---------------------------------------------------------- */
  function processMenuImages(data) {
    if (!data || !data.categories) return data;
    
    var processed = JSON.parse(JSON.stringify(data));
    
    processed.categories.forEach(function(cat) {
      if (cat.items) {
        cat.items.forEach(function(item) {
          if (item.image && !item.image.includes('placeholder')) {
            // Prefer the server-provided stable URL (has /v=<filemtime> and a
            // leading slash). Falls back to the raw path if it is missing.
            item.image_cache = item.image_url || addImageCacheBust(item.image);
          }
        });
      }
      if (cat.subcategories) {
        cat.subcategories.forEach(function(sub) {
          if (sub.items) {
            sub.items.forEach(function(item) {
              if (item.image && !item.image.includes('placeholder')) {
                item.image_cache = item.image_url || addImageCacheBust(item.image);
              }
            });
          }
        });
      }
    });
    
    return processed;
  }

  /* ----------------------------------------------------------
     Initialization
     ---------------------------------------------------------- */
   function init() {
     navInnerEl = document.getElementById("menu-category-nav-inner");
     searchInputEl = document.getElementById("menu-search-input");
     searchClearEl = document.getElementById("menu-search-clear");
     contentEl = document.getElementById("menu-content");
     downloadEl = document.getElementById("menu-download");

     if (!contentEl) {
       console.error("Menu container #menu-content not found");
       return;
     }

     if (window.MENU_INIT_CATEGORY) {
       activeCategory = window.MENU_INIT_CATEGORY;
     }

     renderSkeletons();
     fetchSettings();
     fetchMenuAndSpecials();
     bindSearchEvents();
   }

  /* ----------------------------------------------------------
     Fetch Settings (for WhatsApp number)
     ---------------------------------------------------------- */
  function fetchSettings() {
    fetch("api/settings.php?v=" + Date.now(), { credentials: "same-origin" })
      .then(function (res) {
        if (!res.ok) throw new Error("Settings fetch failed");
        return res.json();
      })
      .then(function (data) {
        settingsData = data;
      })
      .catch(function () {});
  }

  /* ----------------------------------------------------------
     Fetch Menu (Specials disabled to prevent price override)
     ---------------------------------------------------------- */
  function fetchMenuAndSpecials() {
    var menuPromise = fetch(API_MENU + "?v=" + Date.now(), {
      credentials: "same-origin",
    }).then(function (r) {
      if (!r.ok) throw new Error("Menu fetch failed: " + r.status);
      return r.json();
    });

    // SPECIALS API DISABLED - This was causing price overrides
    // var specialsPromise = fetch(API_SPECIALS + "?v=" + Date.now(), {
    //   credentials: "same-origin",
    // }).then(function (r) {
    //   if (!r.ok) throw new Error("Specials fetch failed: " + r.status);
    //   return r.json();
    // });

    // Only wait for menu, not specials
    menuPromise
      .then(function (results) {
        menuData = processMenuImages(results);
        specialsData = null;
        specialItemIds = {};
        clearSkeletons();
        renderNav();
        renderCategories();
        renderDownloadButton();
        setupCategoryScrollObserver();
        setupHeaderAnimationObserver();

        if (activeCategory) {
          var matchedCat = null;
          var cats = getVisibleCategories();
          for (var c = 0; c < cats.length; c++) {
            if (cats[c].id === activeCategory || cats[c].slug === activeCategory) {
              matchedCat = cats[c];
              break;
            }
          }
          if (matchedCat) {
            setActiveCategory(matchedCat.id);
            requestAnimationFrame(function () {
              var section = document.getElementById(
                "menu-category-" + matchedCat.id
              );
              if (section) {
                var y =
                  section.getBoundingClientRect().top +
                  window.pageYOffset -
                  SCROLL_OFFSET;
                window.scrollTo({ top: y, behavior: "smooth" });
              }
            });
          }
        }

        requestAnimationFrame(function () {
          revealCards();
        });
      })
      .catch(function (err) {
        console.error("Failed to load menu:", err);
        clearSkeletons();
        renderError("Unable to load the menu. Please try again later.");
      });
  }

  /* ----------------------------------------------------------
     Build Specials Index - DISABLED (no specials override)
     ---------------------------------------------------------- */
  function buildSpecialsIndex() {
    specialItemIds = {};
    // Specials disabled to prevent price override issues
    // if (!specialsData || !specialsData.specials) return;
    // specialsData.specials.forEach(function (s) {
    //   if (s.enabled !== false) {
    //     specialItemIds[s.item_id] = s;
    //   }
    // });
  }

  /* ----------------------------------------------------------
     Skeleton Loading Cards
     ---------------------------------------------------------- */
  function renderSkeletons() {
    if (!contentEl) return;
    var html = '<div class="menu-skeleton-grid">';
    for (var i = 0; i < SKELETON_COUNT; i++) {
      html +=
        '<div class="menu-skeleton-card">' +
        '<div class="menu-skeleton-card__image"></div>' +
        '<div class="menu-skeleton-card__content">' +
        '<div class="menu-skeleton-card__line menu-skeleton-card__line--title"></div>' +
        '<div class="menu-skeleton-card__line menu-skeleton-card__line--text"></div>' +
        '<div class="menu-skeleton-card__line menu-skeleton-card__line--text-short"></div>' +
        '<div class="menu-skeleton-card__line menu-skeleton-card__line--price"></div>' +
        "</div>" +
        "</div>";
    }
    html += "</div>";
    contentEl.innerHTML = html;
  }

  function clearSkeletons() {
    if (contentEl) contentEl.innerHTML = "";
  }

  /* ----------------------------------------------------------
     Render Sticky Category Navigation Bar
     ---------------------------------------------------------- */
  function renderNav() {
    if (!navInnerEl || !menuData) return;
    var categories = getVisibleCategories();
    var html = "";

    categories.forEach(function (cat) {
      var isActive = activeCategory === cat.id || activeCategory === cat.slug;
      html +=
        '<button class="menu-category-nav__link' +
        (isActive ? " active" : "") +
        '" ' +
        'data-category-id="' +
        escAttr(cat.id) +
        '" ' +
        'role="tab" ' +
        'aria-label="' +
        escAttr(cat.label) +
        '">' +
        "<span>" +
        escHtml(cat.label) +
        "</span>" +
        "</button>";
    });

    navInnerEl.innerHTML = html;

    var buttons = navInnerEl.querySelectorAll(".menu-category-nav__link");
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener("click", handleNavClick);
    }
  }

  function handleNavClick() {
    var catId = this.getAttribute("data-category-id");
    scrollToCategory(catId);
  }

  function scrollToCategory(catId) {
    var section = document.getElementById("menu-category-" + catId);
    if (section) {
      var y =
        section.getBoundingClientRect().top +
        window.pageYOffset -
        SCROLL_OFFSET;
      window.scrollTo({ top: y, behavior: "smooth" });
    }
  }

  function setActiveCategory(catId) {
    if (activeCategory === catId) return;
    activeCategory = catId;
    if (!navInnerEl) return;

    var buttons = navInnerEl.querySelectorAll(".menu-category-nav__link");
    for (var i = 0; i < buttons.length; i++) {
      var btn = buttons[i];
      if (btn.getAttribute("data-category-id") === catId) {
        btn.classList.add("active");
      } else {
        btn.classList.remove("active");
      }
    }

    var activeBtn = navInnerEl.querySelector(".menu-category-nav__link.active");
    if (activeBtn) {
      activeBtn.scrollIntoView({
        behavior: "smooth",
        inline: "center",
        block: "nearest",
      });
    }
  }

  /* ----------------------------------------------------------
     Search Bar Events
     ---------------------------------------------------------- */
  function bindSearchEvents() {
    if (searchInputEl) {
      searchInputEl.addEventListener(
        "input",
        debounce(function () {
          searchQuery = searchInputEl.value.trim().toLowerCase();
          filterBySearch();
          toggleClearButton();
        }, 200),
      );

      searchInputEl.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
          searchInputEl.value = "";
          searchQuery = "";
          filterBySearch();
          toggleClearButton();
          searchInputEl.blur();
        }
      });
    }

    if (searchClearEl) {
      searchClearEl.addEventListener("click", function () {
        if (searchInputEl) searchInputEl.value = "";
        searchQuery = "";
        filterBySearch();
        toggleClearButton();
        if (searchInputEl) searchInputEl.focus();
      });
    }
  }

  function toggleClearButton() {
    if (!searchClearEl) return;
    if (searchQuery.length > 0) {
      searchClearEl.classList.add("menu-search__clear--visible");
    } else {
      searchClearEl.classList.remove("menu-search__clear--visible");
    }
  }

  /* ----------------------------------------------------------
     Search Filter Logic
     ---------------------------------------------------------- */
  function filterBySearch() {
    var allCards = contentEl.querySelectorAll(".menu-item-card");
    var allCategorySections = contentEl.querySelectorAll(".menu-category");
    var allSubcategoryHeadings =
      contentEl.querySelectorAll(".menu-subcategory");

    if (!searchQuery) {
      for (var i = 0; i < allCards.length; i++) {
        allCards[i].classList.remove("search-hidden");
        allCards[i].classList.add("search-match");
      }
      for (var j = 0; j < allCategorySections.length; j++) {
        allCategorySections[j].style.display = "";
      }
      for (var k = 0; k < allSubcategoryHeadings.length; k++) {
        allSubcategoryHeadings[k].style.display = "";
      }
      removeNoResults();
      return;
    }

    var categoryVisible = {};
    var subcategoryVisible = {};

    for (var c = 0; c < allCards.length; c++) {
      var card = allCards[c];
      var name = (card.getAttribute("data-name") || "").toLowerCase();
      var desc = (card.getAttribute("data-description") || "").toLowerCase();
      var badge = (card.getAttribute("data-badge") || "").toLowerCase();
      var price = (card.getAttribute("data-price") || "").toLowerCase();
      var catId = card.getAttribute("data-category-id");
      var subId = card.getAttribute("data-subcategory-id");

      var isMatch =
        name.indexOf(searchQuery) !== -1 ||
        desc.indexOf(searchQuery) !== -1 ||
        badge.indexOf(searchQuery) !== -1 ||
        price.indexOf(searchQuery) !== -1;

      if (isMatch) {
        card.classList.remove("search-hidden");
        card.classList.add("search-match");
        categoryVisible[catId] = true;
        if (subId) {
          subcategoryVisible[catId + "|" + subId] = true;
        }
      } else {
        card.classList.add("search-hidden");
        card.classList.remove("search-match");
      }
    }

    for (var s = 0; s < allCategorySections.length; s++) {
      var section = allCategorySections[s];
      var sectionCatId = section.getAttribute("data-category-id");
      section.style.display = categoryVisible[sectionCatId] ? "" : "none";
    }

    for (var h = 0; h < allSubcategoryHeadings.length; h++) {
      var sub = allSubcategoryHeadings[h];
      var subCatId = sub.getAttribute("data-category-id");
      var subSubId = sub.getAttribute("data-subcategory-id");
      sub.style.display = subcategoryVisible[subCatId + "|" + subSubId]
        ? ""
        : "none";
    }

    var anyMatch = contentEl.querySelector(".menu-item-card.search-match");
    if (!anyMatch) {
      showNoResults();
    } else {
      removeNoResults();
    }
  }

  function showNoResults() {
    if (contentEl.querySelector(".menu-no-results")) return;
    var div = document.createElement("div");
    div.className = "menu-no-results";
    div.innerHTML =
      '<div class="menu-no-results__icon">' +
      '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
      '<circle cx="11" cy="11" r="8"></circle>' +
      '<line x1="21" y1="21" x2="16.65" y2="16.65"></line>' +
      "</svg>" +
      "</div>" +
      '<p class="menu-no-results__text">No dishes found matching "' +
      escHtml(searchQuery) +
      '"</p>';
    contentEl.appendChild(div);
  }

  function removeNoResults() {
    var el = contentEl.querySelector(".menu-no-results");
    if (el) el.remove();
  }

  /* ----------------------------------------------------------
     Render All Category Sections
     ---------------------------------------------------------- */
  function renderCategories() {
    if (!contentEl || !menuData) return;
    var categories = getVisibleCategories();
    var html = "";

    for (var i = 0; i < categories.length; i++) {
      var cat = categories[i];

      html +=
        '<section class="menu-category" id="menu-category-' +
        escAttr(cat.id) +
        '" ' +
        'data-category-id="' +
        escAttr(cat.id) +
        '">';

      html += '<div class="container">';
      html += '<div class="menu-category__header">';
      html += "<div>";
      html += '<h2 class="menu-category__name">' + escHtml(cat.label) + "</h2>";
      if (cat.labelJp) {
        html +=
          '<div class="menu-category__subtitle">' +
          escHtml(cat.labelJp) +
          "</div>";
      }
      html += "</div></div>";

      if (cat.items && cat.items.length > 0) {
        var visibleItems = filterVisibleItems(cat.items);
        if (visibleItems.length > 0) {
          html += renderItemsGrid(visibleItems, cat.id, null);
        }
      }

      if (cat.subcategories && cat.subcategories.length > 0) {
        for (var j = 0; j < cat.subcategories.length; j++) {
          var sub = cat.subcategories[j];
          var subItems = filterVisibleItems(sub.items || []);
          if (subItems.length > 0) {
            var subKey = sub.id || sub.label;
            html +=
              '<div class="menu-subcategory" data-category-id="' +
              escAttr(cat.id) +
              '" ' +
              'data-subcategory-id="' +
              escAttr(subKey) +
              '">' +
              '<h3 class="menu-subcategory__name">' +
              escHtml(sub.label) +
              "</h3>";
            if (sub.labelJp) {
              html +=
                '<p class="menu-subcategory__subtitle">' +
                escHtml(sub.labelJp) +
                "</p>";
            }
            html += "</div>";
            html += renderItemsGrid(subItems, cat.id, subKey);
          }
        }
      }

      html +=
        '<button class="menu-back-to-top" aria-label="Back to top">' +
        SVG_ARROW_UP +
        "</button>";

      html += "</div>";
      html += "</section>";
    }

    contentEl.innerHTML = html;

    var backBtns = contentEl.querySelectorAll(".menu-back-to-top");
    for (var b = 0; b < backBtns.length; b++) {
      backBtns[b].addEventListener("click", function () {
        if (navInnerEl) {
          navInnerEl.scrollIntoView({ behavior: "smooth" });
        } else {
          window.scrollTo({ top: 0, behavior: "smooth" });
        }
      });
    }

    var descriptions = contentEl.querySelectorAll(
      ".menu-item-card__description",
    );
    for (var d = 0; d < descriptions.length; d++) {
      descriptions[d].addEventListener("click", function () {
        this.classList.toggle("expanded");
      });
    }

    bindEnquiryButtons();

    requestAnimationFrame(function () {
      revealCards();
    });
  }

  /* ----------------------------------------------------------
     My Enquiry — temporary list of items to ask about.
     This is NOT a shopping cart: no totals, no checkout,
     no payment. It only pre-fills a WhatsApp enquiry message.
     ---------------------------------------------------------- */
  var ENQUIRY_KEY = "furusato_my_enquiry";

  function enquiryLoad() {
    try {
      return JSON.parse(localStorage.getItem(ENQUIRY_KEY)) || [];
    } catch (e) {
      return [];
    }
  }

  function enquirySave(items) {
    try {
      localStorage.setItem(ENQUIRY_KEY, JSON.stringify(items));
    } catch (e) {}
  }

  function enquiryBuildMessage(items) {
    var msg =
      "Hello Furusato Japanese Restaurant,\n\n" +
      "I would like to enquire about the following menu items:\n\n";
    for (var i = 0; i < items.length; i++) {
      msg += "- " + items[i].name + "\n";
    }
    msg +=
      "\nCould you please confirm availability and provide any relevant information?\n\nThank you.";
    return msg;
  }

  function bindEnquiryButtons() {
    var bar = document.getElementById("enquiry-bar");
    if (!bar) return;

    var countEl = document.getElementById("enquiry-count");
    var listEl = document.getElementById("enquiry-items");
    var sendLink = document.getElementById("enquiry-send-link");
    var panelSend = document.getElementById("enquiry-panel-send");

    function syncButtons() {
      var names = {};
      var items = enquiryLoad();
      for (var i = 0; i < items.length; i++) names[items[i].name] = true;
      var btns = document.querySelectorAll(".menu-item-card__enquire-add");
      for (var j = 0; j < btns.length; j++) {
        var n = btns[j].getAttribute("data-enquiry-name") || "";
        if (names[n]) {
          btns[j].classList.add("added");
          btns[j].textContent = "\u2713 In My Enquiry";
        } else {
          btns[j].classList.remove("added");
          btns[j].textContent = "+ My Enquiry";
        }
      }
    }

    function render() {
      var items = enquiryLoad();
      if (countEl) countEl.textContent = items.length;
      bar.style.display = items.length > 0 ? "flex" : "none";

      if (listEl) {
        listEl.innerHTML = "";
        if (items.length === 0) {
          var li = document.createElement("li");
          li.className = "enquiry-empty";
          li.style.borderBottom = "none";
          li.textContent =
            'Your enquiry list is empty. Tap "+ My Enquiry" on any menu item.';
          listEl.appendChild(li);
        } else {
          for (var k = 0; k < items.length; k++) {
            (function (idx) {
              var it = items[idx];
              var li2 = document.createElement("li");
              var nameSpan = document.createElement("span");
              nameSpan.textContent = it.name;
              li2.appendChild(nameSpan);
              if (it.price) {
                var priceSpan = document.createElement("span");
                priceSpan.className = "enquiry-item-price";
                priceSpan.textContent =
                  "KES " + Number(it.price).toLocaleString();
                li2.appendChild(priceSpan);
              }
              var rm = document.createElement("button");
              rm.type = "button";
              rm.setAttribute("aria-label", "Remove " + it.name);
              rm.innerHTML = "&times;";
              rm.addEventListener("click", function () {
                var cur = enquiryLoad();
                cur.splice(idx, 1);
                enquirySave(cur);
                syncButtons();
                render();
              });
              li2.appendChild(rm);
              listEl.appendChild(li2);
            })(k);
          }
        }
      }

      var href =
        items.length > 0
          ? "https://wa.me/" +
            getWhatsAppNumber() +
            "?text=" +
            encodeURIComponent(enquiryBuildMessage(items))
          : "#";
      if (sendLink) sendLink.setAttribute("href", href);
      if (panelSend) panelSend.setAttribute("href", href);
    }

    var addBtns = document.querySelectorAll(".menu-item-card__enquire-add");
    for (var i = 0; i < addBtns.length; i++) {
      addBtns[i].addEventListener("click", function () {
        var name = this.getAttribute("data-enquiry-name") || "";
        var price = this.getAttribute("data-enquiry-price") || "";
        var items = enquiryLoad();
        var exists = false;
        for (var x = 0; x < items.length; x++) {
          if (items[x].name === name) {
            exists = true;
            break;
          }
        }
        if (exists) {
          items = items.filter(function (it) {
            return it.name !== name;
          });
        } else {
          items.push({ name: name, price: price });
        }
        enquirySave(items);
        syncButtons();
        render();
      });
    }

    var viewBtn = document.getElementById("enquiry-view-btn");
    var panel = document.getElementById("enquiry-panel");
    var closeBtn = document.getElementById("enquiry-close-btn");
    var clearBtn = document.getElementById("enquiry-clear-btn");
    if (viewBtn && panel) {
      viewBtn.addEventListener("click", function () {
        panel.style.display =
          panel.style.display === "none" ? "block" : "none";
      });
    }
    if (closeBtn && panel) {
      closeBtn.addEventListener("click", function () {
        panel.style.display = "none";
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        enquirySave([]);
        syncButtons();
        render();
        if (panel) panel.style.display = "none";
      });
    }

    syncButtons();
    render();
  }

  /* ----------------------------------------------------------
     Render Items Grid - FIXED: No specials price override
     ---------------------------------------------------------- */
  function renderItemsGrid(items, catId, subId) {
    var sorted = items.slice().sort(function (a, b) {
      return (a.order || 0) - (b.order || 0);
    });

    var html = '<div class="menu-items-grid menu-items-grid--stagger">';

    for (var i = 0; i < sorted.length; i++) {
      var item = sorted[i];
      
      // SPECIALS PRICE OVERRIDE REMOVED - Use regular price only
      // The discount is now handled by original_price field from menu API
      var displayPrice = item.price;

      html +=
        '<article class="menu-item-card" ' +
        'data-item-id="' +
        escAttr(item.id) +
        '" ' +
        'data-category-id="' +
        escAttr(catId) +
        '" ' +
        (subId ? 'data-subcategory-id="' + escAttr(subId) + '" ' : "") +
        'data-name="' +
        escAttr(item.name) +
        '" ' +
        'data-description="' +
        escAttr(item.description || "") +
        '" ' +
        'data-badge="' +
        escAttr(item.badge || "") +
        '" ' +
        'data-price="Ksh ' +
        escAttr(formatPrice(displayPrice)) +
        '" ' +
        'style="animation-delay: ' +
        i * STAGGER_DELAY +
        'ms;">';

      html += '<div class="menu-item-card__image-wrap">';
      
      var imageSrc = item.image_cache || item.image;
      if (!imageSrc || imageSrc.trim() === "") {
        imageSrc = "/assets/images/menu/placeholder.webp";
      }
      
      html +=
        '<img class="menu-item-card__image" ' +
        'src="' +
        escAttr(imageSrc) +
        '" ' +
        'alt="' +
        escAttr(item.name) +
        '" ' +
        'loading="lazy" ' +
        'onerror="this.onerror=null;this.src=\'/assets/images/menu/placeholder.webp\'" ' +
        "onload=\"this.classList.add('loaded')\" />";
        
      if (item.badge) {
        html +=
          '<span class="menu-item-card__badge">' +
          escHtml(item.badge) +
          "</span>";
      }
      html += "</div>";

      html += '<div class="menu-item-card__content">';
      html +=
        '<h3 class="menu-item-card__name">' + escHtml(item.name) + "</h3>";

      if (item.description) {
        html +=
          '<p class="menu-item-card__description">' +
          escHtml(item.description) +
          "</p>";
      }

      html += '<div class="menu-item-card__price-row">';
      
      // Show discount styling if original_price exists and is higher than current price
      if (item.original_price && item.original_price > item.price) {
        html += '<div class="menu-item-card__price">';
        html += '<span class="original-price">Ksh ' + formatPrice(item.original_price) + '</span>';
        html += '<span class="current-price">Ksh ' + formatPrice(item.price) + '</span>';
        html += '<span class="discount-badge">Save ' + formatPrice(item.original_price - item.price) + '</span>';
        html += '</div>';
      } else {
        html += '<div class="menu-item-card__price">';
        html += '<span class="current-price">Ksh ' + formatPrice(item.price) + '</span>';
        html += '</div>';
      }

      var whatsappNum = getWhatsAppNumber();
      var waMessage = encodeURIComponent(
        "Hello Furusato Japanese Restaurant team! 👋\n\n" +
          "I would love to place an order for the following item:\n\n" +
          "🍽️ " +
          item.name +
          "\nPrice shown on menu: Ksh " +
          formatPrice(displayPrice) +
          "\n\nWould it be possible for me to order this item with you, please?\n" +
          "I'm really looking forward to enjoying it — thank you kindly! 🙏",
      );
      html +=
        '<a class="menu-item-card__whatsapp" ' +
        'href="https://wa.me/' +
        whatsappNum +
        "?text=" +
        waMessage +
        '" ' +
        'target="_blank" rel="noopener noreferrer">' +
        SVG_WHATSAPP +
        " Order via WhatsApp" +
        "</a>";
      html +=
        '<button type="button" class="menu-item-card__enquire-add" ' +
        'data-enquiry-name="' +
        escAttr(item.name) +
        '" ' +
        'data-enquiry-price="' +
        escAttr(String(item.price || "")) +
        '" ' +
        'aria-label="Add ' +
        escAttr(item.name) +
        ' to My Enquiry">+ My Enquiry</button>';

      html += "</div>";
      html += "</div>";
      html += "</article>";
    }

    html += "</div>";
    return html;
  }

  /* ----------------------------------------------------------
     Render Download PDF Button
     ---------------------------------------------------------- */
  function renderDownloadButton() {
    if (!downloadEl) return;
    downloadEl.innerHTML =
      '<div class="menu-download-pdf__wrap">' +
      '<a class="menu-download-pdf" href="assets/docs/furusato-menu.pdf?v=' + Date.now() + '" target="_blank" rel="noopener">' +
      SVG_DOWNLOAD +
      "<span>Download Menu PDF</span>" +
      "</a>" +
      "</div>";
  }

  /* ----------------------------------------------------------
     Scroll Reveal on Cards (IntersectionObserver)
     ---------------------------------------------------------- */
  var revealObserver = null;

  function revealCards() {
    var cards = contentEl.querySelectorAll(".menu-item-card");

    if (!("IntersectionObserver" in window)) {
      for (var i = 0; i < cards.length; i++) {
        cards[i].classList.add("revealed");
      }
      return;
    }

    revealObserver = new IntersectionObserver(
      function (entries) {
        for (var i = 0; i < entries.length; i++) {
          if (entries[i].isIntersecting) {
            entries[i].target.classList.add("revealed");
            revealObserver.unobserve(entries[i].target);
          }
        }
      },
      {
        root: null,
        rootMargin: "0px 0px -60px 0px",
        threshold: 0.1,
      },
    );

    for (var j = 0; j < cards.length; j++) {
      revealObserver.observe(cards[j]);
    }
  }

  /* ----------------------------------------------------------
     IntersectionObserver for Active Category Nav
     ---------------------------------------------------------- */
  var navObserverSetup = false;

  function setupCategoryScrollObserver() {
    if (navObserverSetup) return;
    navObserverSetup = true;

    if (!("IntersectionObserver" in window)) {
      window.addEventListener(
        "scroll",
        debounce(updateActiveCategoryOnScroll, 100),
      );
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        for (var i = 0; i < entries.length; i++) {
          if (entries[i].isIntersecting) {
            var catId = entries[i].target.getAttribute("data-category-id");
            if (catId) setActiveCategory(catId);
          }
        }
      },
      {
        root: null,
        rootMargin: "-" + SCROLL_OFFSET + "px 0px -60% 0px",
        threshold: 0,
      },
    );

    var sections = contentEl.querySelectorAll(".menu-category");
    for (var s = 0; s < sections.length; s++) {
      observer.observe(sections[s]);
    }
  }

  function updateActiveCategoryOnScroll() {
    var sections = contentEl.querySelectorAll(".menu-category");
    var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    var found = null;

    for (var i = 0; i < sections.length; i++) {
      var top = sections[i].offsetTop - SCROLL_OFFSET - 20;
      if (scrollTop >= top) {
        found = sections[i].getAttribute("data-category-id");
      }
    }

    if (found) setActiveCategory(found);
  }

  /* ----------------------------------------------------------
     IntersectionObserver for Category Header Fade-In
     ---------------------------------------------------------- */
  function setupHeaderAnimationObserver() {
    if (!("IntersectionObserver" in window)) return;

    var observer = new IntersectionObserver(
      function (entries) {
        for (var i = 0; i < entries.length; i++) {
          if (entries[i].isIntersecting) {
            entries[i].target.classList.add("animate-in");
            observer.unobserve(entries[i].target);
          }
        }
      },
      {
        root: null,
        rootMargin: "0px 0px -100px 0px",
        threshold: 0.1,
      },
    );

    var categories = contentEl.querySelectorAll(".menu-category");
    for (var c = 0; c < categories.length; c++) {
      observer.observe(categories[c]);
    }
  }

  /* ----------------------------------------------------------
     Error Rendering
     ---------------------------------------------------------- */
  function renderError(message) {
    if (!contentEl) return;
    contentEl.innerHTML =
      '<div class="menu-no-results">' +
      '<div class="menu-no-results__icon">' +
      '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
      '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>' +
      '<line x1="12" y1="9" x2="12" y2="13"></line>' +
      '<line x1="12" y1="17" x2="12.01" y2="17"></line>' +
      "</svg>" +
      "</div>" +
      '<p class="menu-no-results__text">' +
      escHtml(message) +
      "</p>" +
      '<button class="menu-retry-btn" onclick="location.reload()">Try again</button>' +
      "</div>";
  }

  /* ----------------------------------------------------------
     Utility Helpers
     ---------------------------------------------------------- */
  function getVisibleCategories() {
    if (!menuData || !menuData.categories) return [];
    return menuData.categories
      .filter(function (cat) {
        return cat.visible !== false;
      })
      .sort(function (a, b) {
        return (a.order || 0) - (b.order || 0);
      });
  }

  function filterVisibleItems(items) {
    return (items || []).filter(function (item) {
      return item.visible !== false;
    });
  }

  function getWhatsAppNumber() {
    // Single source of truth: Admin → Settings → WhatsApp (api/settings.php)
    if (
      settingsData &&
      settingsData.whatsapp &&
      settingsData.whatsapp.phone_number
    ) {
      return String(settingsData.whatsapp.phone_number).replace(/[^0-9]/g, "");
    }
    if (settingsData && typeof settingsData.whatsapp === "string") {
      return settingsData.whatsapp.replace(/[^0-9]/g, "");
    }
    return "254734639203";
  }

  function formatPrice(price) {
    if (typeof price !== "number") return String(price);
    return price.toLocaleString("en-KE");
  }

  function escHtml(str) {
    if (!str) return "";
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function escAttr(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var ctx = this;
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(ctx, args);
      }, delay);
    };
  }

  /* ----------------------------------------------------------
     DOM Ready
     ---------------------------------------------------------- */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();