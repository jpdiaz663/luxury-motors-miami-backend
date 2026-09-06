(function (Drupal, once) {
  function isoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function bindBanner(form) {
    const from = form.querySelector("#banner-from");
    const to = form.querySelector("#banner-to");
    const pickup = form.querySelector("#banner-pickup");
    const back = form.querySelector("#banner-return");
    const note = form.querySelector("[data-banner-note]");
    const place = form.querySelector("#banner-place");
    if (!from || !to || !pickup || !back || !note || !place) {
      return;
    }

    const today = new Date();
    const min = isoDate(today);
    pickup.min = min;
    back.min = pickup.value || min;
    if (!pickup.value) {
      const start = new Date(today);
      start.setDate(start.getDate() + 1);
      pickup.value = isoDate(start);
    }
    if (!back.value) {
      const end = new Date(pickup.value);
      end.setDate(end.getDate() + 3);
      back.value = isoDate(end);
    }

    function hotelNeeded() {
      return from.value === "hotel" || to.value === "hotel";
    }

    function syncNote() {
      const show = hotelNeeded();
      note.hidden = !show;
      place.required = show;
      if (!show) {
        place.value = "";
      }
    }

    pickup.addEventListener("change", function () {
      back.min = pickup.value || min;
      if (back.value && back.value < back.min) {
        back.value = back.min;
      }
    });
    from.addEventListener("change", syncNote);
    to.addEventListener("change", syncNote);
    syncNote();

    form.addEventListener("submit", function (event) {
      if (back.value < pickup.value) {
        event.preventDefault();
        back.focus();
        return;
      }
      if (to.value === "same") {
        to.value = from.value;
      }
      form.querySelectorAll("input, select").forEach(function (element) {
        if (!element.name || element.type === "submit" || element.required) {
          return;
        }
        if (!element.value) {
          element.disabled = true;
        }
      });
    });
  }

  function bindRefine(form) {
    form.addEventListener("submit", function () {
      form.querySelectorAll("select").forEach(function (element) {
        if (!element.value) {
          element.disabled = true;
        }
      });
    });
  }

  Drupal.behaviors.lmVehicleSearch = {
    attach: function (context) {
      once("lm-vehicle-search", "[data-banner]", context).forEach(bindBanner);
      once("lm-vehicle-refine", ".fleet-refine", context).forEach(bindRefine);
    },
  };
})(Drupal, once);
