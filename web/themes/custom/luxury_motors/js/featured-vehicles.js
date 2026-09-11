(function (Drupal, once) {
  const INTERVAL = 7000;

  function bindRail(root) {
    const media = root.querySelectorAll("[data-featured-media]");
    const copies = root.querySelectorAll("[data-featured-copy]");
    const thumbs = root.querySelectorAll("[data-featured-thumb]");
    const dots = root.querySelectorAll("[data-featured-dot]");
    const track = root.querySelector("[data-featured-track]");
    const prev = root.querySelector("[data-featured-prev]");
    const next = root.querySelector("[data-featured-next]");
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const last = Math.max(media.length, copies.length, thumbs.length) - 1;
    let index = 0;
    let timer;

    function sync(nextIndex) {
      index = (nextIndex + last + 1) % (last + 1);
      media.forEach(function (item, i) {
        item.classList.toggle("is-active", i === index);
      });
      copies.forEach(function (item, i) {
        const active = i === index;
        item.classList.toggle("is-active", active);
        item.hidden = !active;
      });
      thumbs.forEach(function (item, i) {
        const active = i === index;
        item.classList.toggle("is-active", active);
        item.toggleAttribute("aria-current", active);
        if (active) {
          item.offsetWidth;
        }
      });
      dots.forEach(function (item, i) {
        const active = i === index;
        item.classList.toggle("is-active", active);
        item.toggleAttribute("aria-current", active);
      });
      const activeThumb = thumbs[index];
      if (activeThumb && track) {
        activeThumb.scrollIntoView({
          inline: "center",
          block: "nearest",
          behavior: reduced ? "auto" : "smooth",
        });
      }
    }

    function go(nextIndex) {
      sync(nextIndex);
      restart();
    }

    function restart() {
      window.clearInterval(timer);
      if (reduced || last < 1) {
        return;
      }
      timer = window.setInterval(function () {
        sync(index + 1);
      }, INTERVAL);
    }

    thumbs.forEach(function (thumb) {
      thumb.addEventListener("click", function () {
        go(Number(thumb.dataset.index));
      });
    });
    dots.forEach(function (dot) {
      dot.addEventListener("click", function () {
        go(Number(dot.dataset.index));
      });
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

    root.addEventListener("mouseenter", function () {
      window.clearInterval(timer);
    });
    root.addEventListener("mouseleave", restart);
    root.addEventListener("focusin", function () {
      window.clearInterval(timer);
    });
    root.addEventListener("focusout", function (event) {
      if (!root.contains(event.relatedTarget)) {
        restart();
      }
    });

    restart();
  }

  Drupal.behaviors.lmFeaturedVehicles = {
    attach: function (context) {
      once("lm-featured-vehicles", "[data-featured-vehicles]", context).forEach(bindRail);
    },
  };
})(Drupal, once);
