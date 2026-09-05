/* ============================================================
   Furusato Japanese Restaurant - Main JavaScript
   OPTIMIZED VERSION v2 - Fixed mobile menu, performance, security
   ============================================================ */

(function () {
  "use strict";

  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

  // Prevent multiple initializations
  let isInitialized = false;
  let activeMenuOpen = false;

  /* ----------------------------------------------------------
     1. Navbar Scroll Effect
     ---------------------------------------------------------- */
  function initNavbarScroll() {
    const navbar = $(".navbar");
    if (!navbar) return;

    function onScroll() {
      if (window.scrollY > 50) {
        navbar.classList.add("navbar-scrolled");
      } else {
        navbar.classList.remove("navbar-scrolled");
      }
    }

    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* ----------------------------------------------------------
     2. Mobile Hamburger Menu - PROFESSIONAL REDESIGN
     ---------------------------------------------------------- */
  function initMobileMenu() {
    const hamburger = $(".navbar-hamburger");
    const mobileMenu = $(".mobile-menu");
    const mobileOverlay = $(".mobile-overlay");
    
    if (!hamburger || !mobileMenu) {
      console.warn("[Mobile Menu] Required elements not found");
      return;
    }

    // Create overlay if not exists
    let overlay = mobileOverlay;
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.className = "mobile-overlay";
      document.body.appendChild(overlay);
    }

    function openMenu() {
      mobileMenu.classList.add("open");
      hamburger.classList.add("active");
      overlay.classList.add("active");
      document.body.style.overflow = "hidden";
      document.body.style.position = "fixed";
      document.body.style.width = "100%";
      activeMenuOpen = true;
      
      // Trigger animation for menu items
      const menuItems = $$(".mobile-menu .mobile-nav-link");
      menuItems.forEach((item, index) => {
        item.style.animation = `mobileMenuFadeIn 0.3s ease forwards ${index * 0.05}s`;
      });
    }

    function closeMenu() {
      mobileMenu.classList.remove("open");
      hamburger.classList.remove("active");
      overlay.classList.remove("active");
      document.body.style.overflow = "";
      document.body.style.position = "";
      document.body.style.width = "";
      activeMenuOpen = false;
      
      // Reset animations
      const menuItems = $$(".mobile-menu .mobile-nav-link");
      menuItems.forEach((item) => {
        item.style.animation = "";
      });
    }

    function toggleMenu() {
      if (mobileMenu.classList.contains("open")) {
        closeMenu();
      } else {
        openMenu();
      }
    }

    // Event listeners
    hamburger.addEventListener("click", toggleMenu);
    overlay.addEventListener("click", closeMenu);

    // Close on escape key
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && mobileMenu.classList.contains("open")) {
        closeMenu();
      }
    });

    // Close on link click
    $$(".mobile-menu a").forEach(function (link) {
      link.addEventListener("click", function (e) {
        // Don't close if it's a dropdown toggle
        if (link.classList.contains("dropdown-toggle")) {
          e.preventDefault();
          const parent = link.closest(".mobile-dropdown");
          if (parent) {
            parent.classList.toggle("open");
          }
        } else {
          closeMenu();
        }
      });
    });

    // Handle window resize - close menu if switching to desktop
    let resizeTimer;
    window.addEventListener("resize", function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        if (window.innerWidth > 992 && mobileMenu.classList.contains("open")) {
          closeMenu();
        }
      }, 250);
    });
  }

  /* ----------------------------------------------------------
     3. Active Page Detection
     ---------------------------------------------------------- */
  function initActivePage() {
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split("/").pop() || "index.php";

    // Desktop navigation
    $$(".navbar-nav a").forEach(function (link) {
      const href = link.getAttribute("href");
      if (!href || href === "#" || href === "") return;
      
      const linkPath = href.split("/").pop().split("#")[0].split("?")[0];
      if (linkPath === currentPage || 
          (currentPage === "index.php" && (linkPath === "index.php" || linkPath === ""))) {
        link.classList.add("active");
      } else {
        link.classList.remove("active");
      }
    });

    // Mobile navigation
    $$(".mobile-menu a:not(.dropdown-toggle)").forEach(function (link) {
      const href = link.getAttribute("href");
      if (!href || href === "#" || href === "") return;
      
      const linkPath = href.split("/").pop().split("#")[0].split("?")[0];
      if (linkPath === currentPage || 
          (currentPage === "index.php" && (linkPath === "index.php" || linkPath === ""))) {
        link.classList.add("active");
      } else {
        link.classList.remove("active");
      }
    });
  }

  /* ----------------------------------------------------------
     4. Smooth Scroll for Anchor Links
     ---------------------------------------------------------- */
  function initSmoothScroll() {
    $$('a[href^="#"]').forEach(function (anchor) {
      anchor.addEventListener("click", function (e) {
        const targetId = this.getAttribute("href");
        if (targetId === "#" || targetId === "") return;
        
        const target = $(targetId);
        if (!target) return;
        
        e.preventDefault();
        const navbarHeight = $(".navbar") ? $(".navbar").offsetHeight : 0;
        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navbarHeight;
        
        window.scrollTo({ top: Math.max(0, targetPosition), behavior: "smooth" });
        
        if (history.pushState) {
          history.pushState(null, null, targetId);
        }
      });
    });
  }

  /* ----------------------------------------------------------
     5. Stats Counter Animation
     ---------------------------------------------------------- */
  function initStatsCounter() {
    var statsSection = $(".stats");
    if (!statsSection) return;
    
    var statNumbers = $$(".stat-number", statsSection);
    if (statNumbers.length === 0) return;
    
    var hasAnimated = false;

    function animateCounter(element) {
      var target = parseInt(element.getAttribute("data-target"), 10);
      if (isNaN(target)) return;
      
      var suffix = element.getAttribute("data-suffix") || "";
      var duration = 2000;
      var startTime = null;
      var startValue = 0;
      
      function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        var current = Math.floor(startValue + (eased * target));
        element.textContent = current.toLocaleString() + suffix;
        
        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          element.textContent = target.toLocaleString() + suffix;
        }
      }
      
      requestAnimationFrame(step);
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && !hasAnimated) {
          hasAnimated = true;
          statNumbers.forEach(function (el, index) {
            setTimeout(function () { animateCounter(el); }, index * 150);
          });
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });
    
    observer.observe(statsSection);
  }

  /* ----------------------------------------------------------
     6. Scroll Reveal with Better Performance
     ---------------------------------------------------------- */
  function initScrollReveal() {
    var revealElements = $$(".scroll-reveal");
    if (revealElements.length === 0) return;
    
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("revealed");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: "0px 0px -20px 0px" });
    
    revealElements.forEach(function (el) { observer.observe(el); });
  }

  /* ----------------------------------------------------------
     7. Back to Top Button
     ---------------------------------------------------------- */
  function initBackToTop() {
    var backToTop = $("#back-to-top");
    if (!backToTop) {
      backToTop = document.createElement("button");
      backToTop.id = "back-to-top";
      backToTop.innerHTML = "↑";
      backToTop.setAttribute("aria-label", "Back to top");
      document.body.appendChild(backToTop);
    }
    
    function toggleVisibility() {
      if (window.scrollY > 400) {
        backToTop.classList.add("visible");
      } else {
        backToTop.classList.remove("visible");
      }
    }
    
    backToTop.addEventListener("click", function () { 
      window.scrollTo({ top: 0, behavior: "smooth" }); 
    });
    
    window.addEventListener("scroll", toggleVisibility, { passive: true });
    toggleVisibility();
  }

  /* ----------------------------------------------------------
     8. Menu Category Dropdown - Enhanced
     ---------------------------------------------------------- */
  function initMenuDropdown() {
    var desktopMenu = document.getElementById("nav-menu-categories");
    var mobileMenu = document.getElementById("mobile-menu-categories");
    
    if (!desktopMenu && !mobileMenu) return;

    function renderCategories(categories) {
      var html = '';
      categories.forEach(function(cat) {
        var label = cat.label || cat.name || '';
        var slug = cat.slug || '';
        var categoryId = cat.id || '';
        var param = slug || categoryId;
        html += '<a href="menu.php?category=' + encodeURIComponent(param) + '" class="nav-cat-link">' +
                '<span class="nav-cat-label">' + escapeHtml(label) + '</span>' +
                '</a>';
      });
      
      if (desktopMenu) desktopMenu.innerHTML = html;
      if (mobileMenu) mobileMenu.innerHTML = html;
    }

    function escapeHtml(str) {
      if (!str) return '';
      return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
      });
    }

    // Fetch with timeout and cache busting
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    
    fetch("/api/menu.php", { signal: controller.signal })
      .then(function(res) { 
        clearTimeout(timeoutId);
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json(); 
      })
      .then(function(data) {
        var categories = (data.categories || [])
          .filter(function(c) { return c.visible !== false; })
          .sort(function(a, b) { return (a.order || 0) - (b.order || 0); });
        
        if (categories.length > 0) {
          renderCategories(categories);
        } else {
          useFallbackCategories();
        }
      })
      .catch(function(err) {
        console.error("Failed to load menu categories:", err);
        useFallbackCategories();
      });
      
    function useFallbackCategories() {
      var fallbackCategories = [
        { label: "Specials", slug: "specials" },
        { label: "Starters", slug: "starters" },
        { label: "Sashimi", slug: "sashimi" },
        { label: "Sushi Set", slug: "sushi-set" },
        { label: "Maki Sushi", slug: "maki-sushi" },
        { label: "Set Menu", slug: "set-menu" },
        { label: "Tempura", slug: "tempura" },
        { label: "Noodles", slug: "noodles" },
        { label: "Lunch Special", slug: "lunch-special" },
        { label: "Korean", slug: "korean" },
        { label: "Teppanyaki", slug: "teppanyaki" },
        { label: "Drinks", slug: "drinks" }
      ];
      renderCategories(fallbackCategories);
    }
  }

  /* ----------------------------------------------------------
     9. Review Carousel Auto-Scroll - Fixed
     ---------------------------------------------------------- */
  function initReviewCarousel() {
    var track = $(".reviews-track");
    var container = $(".reviews-container");
    if (!track || !container) return;
    
    var autoScrollInterval = null;
    var scrollAmount = 0;
    var isHovered = false;
    var isScrolling = false;

    function getCardWidth() {
      var reviews = $$(".review-card", track);
      if (reviews.length === 0) return 300;
      var card = reviews[0];
      var style = window.getComputedStyle(card);
      var marginRight = parseFloat(style.marginRight) || 0;
      return card.offsetWidth + marginRight;
    }

    function startAutoScroll() {
      if (autoScrollInterval) clearInterval(autoScrollInterval);
      
      autoScrollInterval = setInterval(function () {
        if (isHovered || isScrolling) return;
        
        var reviews = $$(".review-card", track);
        if (reviews.length === 0) return;
        
        var cardWidth = getCardWidth();
        var maxScroll = track.scrollWidth - container.offsetWidth;
        
        scrollAmount += cardWidth;
        if (scrollAmount >= maxScroll) scrollAmount = 0;
        
        isScrolling = true;
        track.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";
        track.style.transform = "translateX(-" + scrollAmount + "px)";
        
        setTimeout(function() {
          isScrolling = false;
        }, 700);
      }, 5000);
    }
    
    function stopAutoScroll() { 
      if (autoScrollInterval) { 
        clearInterval(autoScrollInterval); 
        autoScrollInterval = null; 
      } 
    }
    
    container.addEventListener("mouseenter", function () { isHovered = true; });
    container.addEventListener("mouseleave", function () { isHovered = false; });
    
    startAutoScroll();
  }

  /* ----------------------------------------------------------
     10. Marquee Ticker Pause on Hover
     ---------------------------------------------------------- */
  function initMarquee() {
    var marquee = $(".marquee");
    if (!marquee) return;
    
    var track = $(".marquee-track", marquee);
    if (!track) return;
    
    marquee.addEventListener("mouseenter", function () { 
      track.style.animationPlayState = "paused"; 
    });
    
    marquee.addEventListener("mouseleave", function () { 
      track.style.animationPlayState = "running"; 
    });
  }

  /* ----------------------------------------------------------
     11. Service Worker Registration - Optimized
     ---------------------------------------------------------- */
  let registrationAttempted = false;
  let notificationShown = false;

  function showUpdateNotification() {
    if (notificationShown) return;
    notificationShown = true;
    
    var notification = document.createElement('div');
    notification.className = 'sw-update-notification';
    notification.setAttribute('role', 'alert');
    notification.innerHTML = `
      <div class="sw-update-content">
        <span>🔄 New version available!</span>
        <button class="update-btn">Update Now</button>
      </div>
    `;
    
    var btn = notification.querySelector('.update-btn');
    btn.onclick = function() {
      window.location.reload();
    };
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
      if (notification.parentNode) {
        notification.style.opacity = '0';
        setTimeout(function() {
          if (notification.parentNode) notification.remove();
          notificationShown = false;
        }, 300);
      }
    }, 10000);
  }

  function initServiceWorker() {
    if (!("serviceWorker" in navigator)) {
      console.log("[SW] Service workers not supported");
      return;
    }
    
    if (registrationAttempted) {
      return;
    }
    registrationAttempted = true;
    
    navigator.serviceWorker.register("/sw.js")
      .then(function(registration) {
        console.log("[SW] Registration successful, scope:", registration.scope);
        
        // Check for updates every hour
        setInterval(function() {
          registration.update().catch(function(err) {
            console.log("[SW] Update check failed:", err);
          });
        }, 60 * 60 * 1000);
        
        registration.addEventListener("updatefound", function() {
          var newWorker = registration.installing;
          
          newWorker.addEventListener("statechange", function() {
            if (newWorker.state === "installed" && navigator.serviceWorker.controller) {
              showUpdateNotification();
            }
          });
        });
      })
      .catch(function(err) {
        console.log("[SW] Registration failed:", err);
        registrationAttempted = false;
      });
  }

  /* ----------------------------------------------------------
     12. Popular Dishes - Optimized Loading
     ---------------------------------------------------------- */
  function initPopularDishes() {
    const container = document.getElementById('dishes-grid');
    
    if (!container) {
      return;
    }
    
    container.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Loading popular dishes...</p></div>';
    
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);
    
    fetch('/api/menu.php?popular=true', { signal: controller.signal })
      .then(function(response) {
        clearTimeout(timeoutId);
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function(data) {
        let dishes = [];
        if (data.popular && Array.isArray(data.popular)) {
          dishes = data.popular;
        } else if (Array.isArray(data)) {
          dishes = data;
        } else if (data.data && Array.isArray(data.data)) {
          dishes = data.data;
        }
        
        if (!dishes.length) {
          container.innerHTML = '<div class="error-message"><p>No popular dishes found at the moment.</p><p style="font-size: 0.9rem; margin-top: 10px;">Please check back later or <a href="/menu.php" style="color: #d4af37;">view our full menu</a>.</p></div>';
          return;
        }
        
        let html = '';
        for (let i = 0; i < Math.min(dishes.length, 6); i++) {
          const dish = dishes[i];
          const badge = dish.badge || 'Popular';
          const name = escapeHtml(dish.name) || 'Untitled';
          const description = escapeHtml((dish.description || 'A delicious Japanese specialty').substring(0, 100));
          const price = Number(dish.price || 0).toLocaleString();
          
          let imageUrl = dish.image_url || dish.image || '/assets/images/menu/placeholder.webp';
          if (imageUrl && !imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
            imageUrl = '/' + imageUrl;
          }
          
          html += `
            <div class="dish-card scroll-reveal">
              <div class="dish-card-image">
                <span class="dish-badge">${badge}</span>
                <img src="${imageUrl}" 
                     alt="${name}" 
                     loading="lazy"
                     width="300"
                     height="200"
                     onerror="this.onerror=null; this.src='/assets/images/furusato-logo.png'">
              </div>
              <div class="dish-card-body">
                <h3 class="dish-card-name">${name}</h3>
                <p class="dish-card-desc">${description}${description.length >= 98 ? '...' : ''}</p>
                <div class="dish-card-footer">
                  <span class="dish-card-price">KES ${price}</span>
                </div>
              </div>
            </div>
          `;
        }
        
        container.innerHTML = html;
        
        // Observe new elements for scroll reveal
        const newRevealElements = $$(".scroll-reveal", container);
        const observer = new IntersectionObserver(function(entries) {
          entries.forEach(function(entry) {
            if (entry.isIntersecting) {
              entry.target.classList.add("revealed");
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.1 });
        
        newRevealElements.forEach(function(el) {
          observer.observe(el);
        });
      })
      .catch(function(error) {
        console.error('[Popular Dishes] Error:', error);
        container.innerHTML = '<div class="error-message"><p>Unable to load popular dishes. Please refresh the page.</p><button class="retry-btn" onclick="location.reload()">Retry</button><p style="margin-top: 15px; font-size: 0.8rem;">Or <a href="/menu.php" style="color: #d4af37;">browse our full menu</a></p></div>';
      });
    
    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }
  }

  /* ----------------------------------------------------------
     13. Lazy Load Images
     ---------------------------------------------------------- */
  function initLazyLoad() {
    if ('loading' in HTMLImageElement.prototype) {
      const images = document.querySelectorAll('img[loading="lazy"]');
      images.forEach(img => {
        if (img.dataset.src) {
          img.src = img.dataset.src;
        }
      });
    } else {
      // Fallback for older browsers
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/intersection-observer@0.12.0/intersection-observer.min.js';
      document.head.appendChild(script);
    }
  }

  /* ----------------------------------------------------------
     14. Fix missing favicon & video poster
     ---------------------------------------------------------- */
  function initFavicon() {
    var favicon = document.querySelector("link[rel='icon']");
    if (!favicon) {
      favicon = document.createElement("link");
      favicon.rel = "icon";
      favicon.type = "image/x-icon";
      favicon.href = "data:image/x-icon;base64,AAABAAEAEBAAAAEAIABoBAAAFgAAACgAAAAQAAAAIAAAAAEAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADAwMDBEREREZGRkZJSUlJTExMTE9PT09SUlJSVRUVFRYWFhYWVlZWVlXV1dXV1dXV1ZWVlZWWVlZWVJSUlI+Pj4+Li4uLiEhISEWFhYWDg4ODgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=";
      document.head.appendChild(favicon);
    }
  }

  function initVideoPoster() {
    var video = document.querySelector("video");
    if (video && video.hasAttribute("poster")) {
      var posterUrl = video.getAttribute("poster");
      if (posterUrl) {
        var img = new Image();
        img.onerror = function() {
          video.removeAttribute("poster");
        };
        img.src = posterUrl;
      }
    }
  }

  /* ----------------------------------------------------------
     15. Add CSS Keyframes for Mobile Menu
     ---------------------------------------------------------- */
  function addMobileMenuStyles() {
    if (!document.querySelector('#mobile-menu-styles')) {
      var style = document.createElement('style');
      style.id = 'mobile-menu-styles';
      style.textContent = `
        @keyframes mobileMenuFadeIn {
          from {
            opacity: 0;
            transform: translateX(-20px);
          }
          to {
            opacity: 1;
            transform: translateX(0);
          }
        }
        
        @keyframes slideUp {
          from {
            opacity: 0;
            transform: translateX(-50%) translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
          }
        }
        
        .mobile-overlay {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          z-index: 998;
          opacity: 0;
          visibility: hidden;
          transition: all 0.3s ease;
        }
        
        .mobile-overlay.active {
          opacity: 1;
          visibility: visible;
        }
        
        #back-to-top {
          position: fixed;
          bottom: 96px;
          right: 28px;
          width: 48px;
          height: 48px;
          border-radius: 50%;
          background: #0d1b2a;
          color: #d4af7a;
          font-size: 1.25rem;
          border: 2px solid #d4af7a;
          cursor: pointer;
          z-index: 899;
          opacity: 0;
          visibility: hidden;
          transition: all 0.3s ease;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        
        #back-to-top.visible {
          opacity: 1;
          visibility: visible;
        }
        
        #back-to-top:hover {
          background: #d4af7a;
          color: #0d1b2a;
          transform: translateY(-3px);
        }
      `;
      document.head.appendChild(style);
    }
  }

  /* ----------------------------------------------------------
     16. Initialize All Modules
     ---------------------------------------------------------- */
  function init() {
    if (isInitialized) return;
    isInitialized = true;
    
    addMobileMenuStyles();
    initNavbarScroll();
    initMobileMenu();
    initActivePage();
    initSmoothScroll();
    initStatsCounter();
    initScrollReveal();
    initBackToTop();
    initMenuDropdown();
    initReviewCarousel();
    initMarquee();
    initServiceWorker();
    initFavicon();
    initVideoPoster();
    initLazyLoad();
    
    console.log("[Furusato] All modules initialized");
  }

  // Start initialization
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();