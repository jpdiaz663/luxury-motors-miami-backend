(function (Drupal, once, drupalSettings) {
  function isoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function termId(value) {
    const match = String(value || "").match(/\((\d+)\)\s*$/);
    if (match) {
      return match[1];
    }
    return /^\d+$/.test(String(value || "").trim()) ? String(value).trim() : "";
  }

  function bindBanner(form) {
    const from = form.querySelector("#banner-from");
    const to = form.querySelector("#banner-to");
    const fromId = form.querySelector("#banner-from-id");
    const toId = form.querySelector("#banner-to-id");
    const pickup = form.querySelector("#banner-pickup");
    const back = form.querySelector("#banner-return");
    const note = form.querySelector("[data-banner-note]");
    const place = form.querySelector("#banner-place");
    const submit = form.querySelector("[data-banner-submit]");
    const reset = form.querySelector("[data-banner-reset]");
    const settings = drupalSettings.lmVehicleSearch || {};
    const requiresPlace = (settings.requiresPlace || []).map(Number);

    if (!from || !to || !fromId || !toId || !pickup || !back || !note || !place) {
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

    function syncIds() {
      fromId.value = termId(from.value);
      const delivery = termId(to.value);
      toId.value = delivery || fromId.value;
    }

    function hotelNeeded() {
      return requiresPlace.includes(Number(termId(from.value)))
        || requiresPlace.includes(Number(termId(to.value)));
    }

    function syncNote() {
      const show = hotelNeeded();
      note.hidden = !show;
      place.required = show;
      if (!show) {
        place.value = "";
      }
    }

    function setBusy(busy) {
      form.setAttribute("aria-busy", busy ? "true" : "false");
      if (submit) {
        submit.disabled = busy;
        submit.classList.toggle("is-busy", busy);
      }
    }

    pickup.addEventListener("change", function () {
      back.min = pickup.value || min;
      if (back.value && back.value < back.min) {
        back.value = back.min;
      }
    });
    ["change", "blur", "autocompleteclose", "autocompleteselect"].forEach(function (eventName) {
      from.addEventListener(eventName, function () {
        syncIds();
        syncNote();
      });
      to.addEventListener(eventName, function () {
        syncIds();
        syncNote();
      });
    });
    syncIds();
    syncNote();

    if (form.classList.contains("is-need-dates") && pickup) {
      const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      form.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "center" });
      pickup.focus();
    }

    if (reset) {
      reset.addEventListener("click", function (event) {
        if (window.location.search && settings.resetUrl) {
          return;
        }
        event.preventDefault();
        from.value = "";
        to.value = "";
        fromId.value = "";
        toId.value = "";
        place.value = "";
        const start = new Date(today);
        start.setDate(start.getDate() + 1);
        pickup.value = isoDate(start);
        const end = new Date(start);
        end.setDate(end.getDate() + 3);
        back.value = isoDate(end);
        const ptime = form.querySelector("#banner-ptime");
        const rtime = form.querySelector("#banner-rtime");
        if (ptime) {
          ptime.value = "10:00";
        }
        if (rtime) {
          rtime.value = "10:00";
        }
        setBusy(false);
        syncNote();
      });
    }

    form.addEventListener("submit", function (event) {
      syncIds();
      if (!fromId.value) {
        event.preventDefault();
        from.focus();
        return;
      }
      if (back.value < pickup.value) {
        event.preventDefault();
        back.focus();
        return;
      }
      form.querySelectorAll("[data-banner-lookup]").forEach(function (element) {
        element.disabled = true;
      });
      form.querySelectorAll("input, select").forEach(function (element) {
        if (!element.name || element.type === "submit" || element.required) {
          return;
        }
        if (!element.value) {
          element.disabled = true;
        }
      });
      setBusy(true);
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
})(Drupal, once, drupalSettings);
