/* ============================================================
   Furusato Japanese Restaurant — Hardcoded Hero Slideshow
   Pure crossfade carousel; no API fetch.
   Uses the 8 hardcoded slides from index.php.
   ============================================================ */

(function () {
  "use strict";

  var CONFIG = {
    autoAdvanceInterval: 5000,  // 5 s per slide
    crossfadeDuration:    800,  // ms
  };

  var state = {
    currentIndex:  0,
    slideCount:    0,
    timer:         null,
    isPaused:      false,
    isTransitioning: false,
    heroEl:        null,
    dotsEl:        null,
  };

  /* ── Init ── */
  function init() {
    state.heroEl = document.querySelector(".hero");
    if (!state.heroEl) return;

    state.dotsEl = document.querySelector(".hero-dots");

    var slides = $$(".hero-slide", state.heroEl);
    state.slideCount = slides.length;
    if (state.slideCount === 0) return;

    applyTransitionDuration(slides);
    initAutoAdvance();
    initArrows();
    initDots();
    initPauseOnHover();
    initKeyboardNav();
    initTouchSwipe();
    initVisibilityHandler();
  }

  /* ── Helpers ── */
  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); };

  function goToSlide(index) {
    if (state.isTransitioning) return;
    if (state.slideCount <= 1) return;

    if (index < 0)       index = state.slideCount - 1;
    if (index >= state.slideCount) index = 0;

    state.isTransitioning = true;

    var slides = $$(".hero-slide", state.heroEl);
    var dots   = $$(".hero-dot",    state.dotsEl);

    slides.forEach(function (s) { s.classList.remove("active"); });
    dots   && dots.forEach(function (d) { d.classList.remove("active"); });

    slides[index].classList.add("active");
    dots   && dots   [index].classList.add("active");

    state.currentIndex = index;

    setTimeout(function () {
      state.isTransitioning = false;
    }, CONFIG.crossfadeDuration);
  }

  /* ── Transitions ── */
  function applyTransitionDuration(slides) {
    slides.forEach(function (s) {
      s.style.transition = "opacity " + CONFIG.crossfadeDuration + "ms ease";
    });
  }

  /* ── Auto advance ── */
  function initAutoAdvance() {
    state.timer = setInterval(function () {
      if (!state.isPaused) goToSlide(state.currentIndex + 1);
    }, CONFIG.autoAdvanceInterval);
  }

  function resetAutoAdvance() {
    clearInterval(state.timer);
    state.timer = setInterval(function () {
      if (!state.isPaused) goToSlide(state.currentIndex + 1);
    }, CONFIG.autoAdvanceInterval);
  }

  /* ── Arrows ── */
  function initArrows() {
    $(".hero-arrow-prev", state.heroEl) &&
      $(".hero-arrow-prev", state.heroEl).addEventListener("click", function () {
        goToSlide(state.currentIndex - 1);
        resetAutoAdvance();
      });

    $(".hero-arrow-next", state.heroEl) &&
      $(".hero-arrow-next", state.heroEl).addEventListener("click", function () {
        goToSlide(state.currentIndex + 1);
        resetAutoAdvance();
      });
  }

  /* ── Dots ── */
  function initDots() {
    var dots = $$(".hero-dot", state.dotsEl);
    dots.forEach(function (dot) {
      dot.addEventListener("click", function () {
        goToSlide(parseInt(dot.dataset.index, 10));
        resetAutoAdvance();
      });
    });
  }

  /* ── Pause on hover ── */
  function initPauseOnHover() {
    state.heroEl.addEventListener("mouseenter", function () { state.isPaused = true;  });
    state.heroEl.addEventListener("mouseleave", function () { state.isPaused = false; });
  }

  /* ── Keyboard: ← / → ── */
  function initKeyboardNav() {
    document.addEventListener("keydown", function (e) {
      if (!state.heroEl) return;
      var rect = state.heroEl.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > window.innerHeight) return;

      if (e.key === "ArrowLeft")  { e.preventDefault(); goToSlide(state.currentIndex - 1); resetAutoAdvance(); }
      if (e.key === "ArrowRight") { e.preventDefault(); goToSlide(state.currentIndex + 1); resetAutoAdvance(); }
    });
  }

  /* ── Touch swipe ── */
  function initTouchSwipe() {
    var startX = 0, startY = 0, endX = 0, endY = 0, threshold = 50;

    state.heroEl.addEventListener("touchstart", function (e) {
      startX = e.changedTouches[0].screenX;
      startY = e.changedTouches[0].screenY;
    }, { passive: true });

    state.heroEl.addEventListener("touchmove", function (e) {
      endX = e.changedTouches[0].screenX;
      endY = e.changedTouches[0].screenY;
    }, { passive: true });

    state.heroEl.addEventListener("touchend", function (e) {
      endX = e.changedTouches[0].screenX;
      endY = e.changedTouches[0].screenY;
      var dx = endX - startX;
      var dy = endY - startY;
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > threshold) {
        goToSlide(state.currentIndex + (dx < 0 ? 1 : -1));
        resetAutoAdvance();
      }
    }, { passive: true });
  }

  /* ── Visibility: pause when tab hidden ── */
  function initVisibilityHandler() {
    document.addEventListener("visibilitychange", function () {
      if (document.hidden)       { clearInterval(state.timer); }
      else                       { initAutoAdvance(); }
    });
  }

  /* ── Run ── */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
