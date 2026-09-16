(function (Drupal, once) {
  // Long enough to read label + detail and notice the next card peeking.
  const INTERVAL = 5000;
  const CAROUSEL = "(max-width: 640px)";

  function bindList(list) {
    const items = Array.from(list.querySelectorAll(".fare-inclusions__item"));
    const carousel = window.matchMedia(CAROUSEL);
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
    let index = 0;
    let timer;
    let inView = true;
    let paused = false;
    let programmatic = false;

    function canPlay() {
      return (
        carousel.matches &&
        !reduced.matches &&
        items.length > 1 &&
        inView &&
        !paused &&
        !document.hidden
      );
    }

    function stop() {
      window.clearInterval(timer);
      timer = undefined;
    }

    function restart() {
      stop();
      if (!canPlay()) {
        return;
      }
      timer = window.setInterval(function () {
        go(index + 1);
      }, INTERVAL);
    }

    function scrollToIndex(nextIndex, wrapInstant) {
      const length = items.length;
      index = ((nextIndex % length) + length) % length;
      const origin = items[0].offsetLeft;
      const behavior = reduced.matches || wrapInstant ? "auto" : "smooth";
      programmatic = true;
      list.scrollTo({
        left: items[index].offsetLeft - origin,
        behavior: behavior,
      });
      window.setTimeout(function () {
        programmatic = false;
      }, 450);
    }

    function go(nextIndex) {
      const length = items.length;
      const wrapped = nextIndex % length;
      const looping = wrapped === 0 && index === length - 1;
      scrollToIndex(nextIndex, looping);
      restart();
    }

    function nearestIndex() {
      const origin = items[0].offsetLeft;
      const left = list.scrollLeft + origin;
      let nearest = 0;
      let best = Infinity;
      items.forEach(function (item, i) {
        const delta = Math.abs(item.offsetLeft - left);
        if (delta < best) {
          best = delta;
          nearest = i;
        }
      });
      return nearest;
    }

    list.addEventListener(
      "scroll",
      function () {
        if (programmatic) {
          return;
        }
        index = nearestIndex();
        restart();
      },
      { passive: true }
    );

    ["pointerdown", "touchstart", "focusin"].forEach(function (eventName) {
      list.addEventListener(eventName, function () {
        paused = true;
        stop();
      });
    });

    ["pointerup", "pointercancel", "touchend", "focusout"].forEach(function (eventName) {
      list.addEventListener(eventName, function (event) {
        if (eventName === "focusout" && list.contains(event.relatedTarget)) {
          return;
        }
        paused = false;
        index = nearestIndex();
        restart();
      });
    });

    const vis = new IntersectionObserver(
      function (entries) {
        inView = Boolean(entries[0] && entries[0].isIntersecting);
        if (inView) {
          restart();
        } else {
          stop();
        }
      },
      { threshold: 0.4 }
    );
    vis.observe(list);

    document.addEventListener("visibilitychange", function () {
      if (document.hidden) {
        stop();
      } else {
        restart();
      }
    });

    carousel.addEventListener("change", function () {
      if (carousel.matches) {
        restart();
      } else {
        stop();
        list.scrollTo({ left: 0 });
        index = 0;
      }
    });
    reduced.addEventListener("change", restart);

    restart();
  }

  Drupal.behaviors.lmFareInclusions = {
    attach: function (context) {
      once("lm-fare-inclusions", "[data-fare-inclusions-list]", context).forEach(bindList);
    },
  };
})(Drupal, once);
