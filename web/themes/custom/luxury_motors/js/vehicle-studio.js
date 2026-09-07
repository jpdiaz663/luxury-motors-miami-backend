(function () {
  document.querySelectorAll("[data-vehicle-studio]").forEach(function (root) {
    var slides = root.querySelectorAll("[data-gallery-slide]");
    var thumbs = root.querySelectorAll("[data-gallery-thumb]");
    thumbs.forEach(function (thumb, index) {
      thumb.addEventListener("click", function () {
        var slide = slides[index];
        if (slide) {
          slide.scrollIntoView({ inline: "center", block: "nearest", behavior: "smooth" });
        }
        thumbs.forEach(function (item) {
          item.classList.toggle("is-active", item === thumb);
        });
      });
    });
  });
})();
