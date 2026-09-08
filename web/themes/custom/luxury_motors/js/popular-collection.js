(function (Drupal, once) {
  function bindChooser(root) {
    const track = root.querySelector("[data-chooser]");
    const slides = track ? Array.from(track.querySelectorAll(".chooser-slide")) : [];
    const pips = Array.from(root.querySelectorAll("[data-chooser-pip]"));
    const prev = root.querySelector("[data-chooser-prev]");
    const next = root.querySelector("[data-chooser-next]");
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (!track || slides.length < 1) {
      return;
    }

    let index = 0;
    let drag = null;
    let suppressClick = false;

    function behavior() {
      return reduced ? "auto" : "smooth";
    }

    function sync(nextIndex) {
      index = Math.max(0, Math.min(slides.length - 1, nextIndex));
      slides.forEach(function (slide, i) {
        slide.classList.toggle("is-active", i === index);
      });
      pips.forEach(function (pip, i) {
        const active = i === index;
        pip.classList.toggle("is-active", active);
        pip.toggleAttribute("aria-current", active);
      });
    }

    function go(nextIndex) {
      const target = slides[Math.max(0, Math.min(slides.length - 1, nextIndex))];
      if (!target) {
        return;
      }
      target.scrollIntoView({
        inline: "center",
        block: "nearest",
        behavior: behavior(),
      });
      sync(slides.indexOf(target));
    }

    const observer = new IntersectionObserver(
      function (entries) {
        const visible = entries
          .filter(function (entry) {
            return entry.isIntersecting;
          })
          .sort(function (a, b) {
            return b.intersectionRatio - a.intersectionRatio;
          })[0];
        if (!visible) {
          return;
        }
        const nextIndex = slides.indexOf(visible.target);
        if (nextIndex >= 0) {
          sync(nextIndex);
        }
      },
      {
        root: track,
        threshold: [0.55, 0.75],
      }
    );
    slides.forEach(function (slide) {
      observer.observe(slide);
    });

    if (prev) {
      prev.addEventListener("click", function () {
        go(index - 1);
      });
    }
    if (next) {
      next.addEventListener("click", function () {
        go(index + 1);
      });
    }
    pips.forEach(function (pip) {
      pip.addEventListener("click", function () {
        go(Number(pip.dataset.index));
      });
    });

    track.addEventListener("pointerdown", function (event) {
      if (event.pointerType === "mouse" && event.button !== 0) {
        return;
      }
      drag = {
        id: event.pointerId,
        startX: event.clientX,
        scroll: track.scrollLeft,
        moved: false,
      };
      track.classList.add("is-dragging");
      track.setPointerCapture(event.pointerId);
    });
    track.addEventListener("pointermove", function (event) {
      if (!drag || event.pointerId !== drag.id) {
        return;
      }
      const delta = event.clientX - drag.startX;
      if (Math.abs(delta) > 4) {
        drag.moved = true;
      }
      track.scrollLeft = drag.scroll - delta;
    });
    function endDrag(event) {
      if (!drag || event.pointerId !== drag.id) {
        return;
      }
      track.classList.remove("is-dragging");
      if (drag.moved) {
        suppressClick = true;
      }
      drag = null;
    }
    track.addEventListener("pointerup", endDrag);
    track.addEventListener("pointercancel", endDrag);
    track.addEventListener("click", function (event) {
      if (suppressClick) {
        event.preventDefault();
        event.stopPropagation();
        suppressClick = false;
      }
    }, true);

    root.addEventListener("keydown", function (event) {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        go(index - 1);
      }
      if (event.key === "ArrowRight") {
        event.preventDefault();
        go(index + 1);
      }
    });
  }

  Drupal.behaviors.lmPopularCollection = {
    attach: function (context) {
      once("lm-popular-collection", ".chooser", context).forEach(bindChooser);
    },
  };
})(Drupal, once);
