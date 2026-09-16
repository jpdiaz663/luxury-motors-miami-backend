(function (Drupal, once, drupalSettings) {
  function isoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function parseDay(value) {
    const parts = String(value || "").split("-");
    if (parts.length !== 3) {
      return null;
    }
    const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function combineDateTime(dateStr, timeStr) {
    const date = parseDay(dateStr);
    if (!date) {
      return null;
    }
    const bits = String(timeStr || "10:00").split(":");
    date.setHours(Number(bits[0]) || 0, Number(bits[1]) || 0, 0, 0);
    return date;
  }

  function searchSettings() {
    return drupalSettings.lmVehicleSearch || {};
  }

  function earliestPickupDate() {
    const settings = searchSettings();
    if (settings.earliestPickup) {
      const parsed = new Date(settings.earliestPickup);
      if (!Number.isNaN(parsed.getTime())) {
        return parsed;
      }
    }
    const fallback = new Date();
    fallback.setHours(fallback.getHours() + (Number(settings.leadHours) || 24));
    return fallback;
  }

  function minReturnDate(pickup, ptime) {
    const start = combineDateTime(pickup, ptime);
    if (!start) {
      return null;
    }
    const hours = Number(searchSettings().minRentalHours || 0);
    if (hours <= 0) {
      return start;
    }
    return new Date(start.getTime() + hours * 3600000);
  }

  function tripIssueFromValues(pickup, ptime, back, rtime) {
    const start = combineDateTime(pickup, ptime);
    const end = combineDateTime(back, rtime);
    const earliest = earliestPickupDate();
    if (!start || start < earliest) {
      return "lead";
    }
    const minReturn = minReturnDate(pickup, ptime);
    if (!end || !minReturn || end < minReturn) {
      return "return";
    }
    return null;
  }

  function termId(value) {
    const match = String(value || "").match(/\((\d+)\)\s*$/);
    if (match) {
      return match[1];
    }
    return /^\d+$/.test(String(value || "").trim()) ? String(value).trim() : "";
  }

  function labelOnly(value) {
    return String(value || "").replace(/\s*\(\d+\)\s*$/, "").trim();
  }

  function closeTimeDrops(except) {
    document.querySelectorAll(".lm-time-drop.is-open").forEach(function (drop) {
      if (except && drop === except) {
        return;
      }
      drop.classList.remove("is-open");
      const trigger = drop.querySelector(".lm-time-drop__trigger");
      const panel = drop.querySelector(".lm-time-drop__panel");
      if (trigger) {
        trigger.setAttribute("aria-expanded", "false");
      }
      if (panel) {
        panel.hidden = true;
      }
    });
  }

  function refreshTimeDrop(select) {
    if (select && typeof select.lmRefreshTimeDrop === "function") {
      select.lmRefreshTimeDrop();
    }
  }

  function bindTimeDrop(select) {
    if (!select || select.closest(".lm-time-drop")) {
      return;
    }
    const wrap = document.createElement("div");
    wrap.className = "lm-time-drop";
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add("lm-time-drop__native");
    select.setAttribute("tabindex", "-1");
    select.setAttribute("aria-hidden", "true");

    const trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "lm-time-drop__trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");
    trigger.id = (select.id || "banner-time") + "-trigger";

    const panel = document.createElement("div");
    panel.className = "lm-time-drop__panel";
    panel.setAttribute("role", "listbox");
    panel.setAttribute("aria-labelledby", trigger.id);
    panel.hidden = true;

    function selectedLabel() {
      return select.selectedOptions[0] ? select.selectedOptions[0].textContent.trim() : "";
    }

    function close() {
      wrap.classList.remove("is-open");
      trigger.setAttribute("aria-expanded", "false");
      panel.hidden = true;
    }

    function renderOptions() {
      panel.replaceChildren();
      Array.prototype.forEach.call(select.options, function (option) {
        const item = document.createElement("button");
        item.type = "button";
        item.className = "lm-time-drop__option";
        item.setAttribute("role", "option");
        item.dataset.value = option.value;
        item.textContent = option.textContent.trim();
        item.disabled = option.disabled;
        const selected = option.selected || option.value === select.value;
        item.setAttribute("aria-selected", selected ? "true" : "false");
        if (selected) {
          item.classList.add("is-selected");
        }
        item.addEventListener("click", function () {
          if (option.disabled) {
            return;
          }
          select.value = option.value;
          select.dispatchEvent(new Event("change", { bubbles: true }));
          close();
          trigger.focus();
        });
        panel.appendChild(item);
      });
      trigger.textContent = selectedLabel();
    }

    function open() {
      closeTimeDrops(wrap);
      renderOptions();
      wrap.classList.add("is-open");
      trigger.setAttribute("aria-expanded", "true");
      panel.hidden = false;
      const selected = panel.querySelector(".is-selected") || panel.querySelector(".lm-time-drop__option:not(:disabled)");
      if (selected) {
        selected.focus();
        selected.scrollIntoView({ block: "nearest" });
      }
    }

    trigger.addEventListener("click", function (event) {
      event.preventDefault();
      if (wrap.classList.contains("is-open")) {
        close();
      }
      else {
        open();
      }
    });
    trigger.addEventListener("keydown", function (event) {
      if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        open();
      }
    });
    wrap.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        event.preventDefault();
        close();
        trigger.focus();
      }
    });
    wrap.appendChild(trigger);
    wrap.appendChild(panel);
    select.lmRefreshTimeDrop = renderOptions;
    renderOptions();
  }

  function bindBanner(form) {
    const from = form.querySelector("#banner-from");
    const to = form.querySelector("#banner-to");
    const fromId = form.querySelector("#banner-from-id") || form.querySelector('input[name="from"]');
    const toId = form.querySelector("#banner-to-id") || form.querySelector('input[name="to"]');
    const pickup = form.querySelector("#banner-pickup");
    const back = form.querySelector("#banner-return");
    const ptime = form.querySelector("#banner-ptime");
    const rtime = form.querySelector("#banner-rtime");
    const dateHint = form.querySelector("[data-banner-date-hint]");
    const note = form.querySelector("[data-banner-note]");
    const place = form.querySelector("#banner-place");
    const submit = form.querySelector("[data-banner-submit]");
    const reset = form.querySelector("[data-banner-reset]");
    const settings = searchSettings();
    const requiresPlace = (settings.requiresPlace || []).map(Number);
    const collapse = form.querySelector("[data-banner-collapse]");
    const panel = form.querySelector("[data-banner-panel]");
    const summary = form.querySelector("[data-banner-summary]");
    const collapseLabel = form.querySelector("[data-banner-collapse-label]");
    const storageKey = "lmFleetSearchDock";

    if (!from || !to || !fromId || !toId || !pickup || !back || !note || !place) {
      return;
    }

    const defaults = settings.defaultWindow || {};
    if (!pickup.value && defaults.pickup) {
      pickup.value = defaults.pickup;
    }
    if (!back.value && defaults.return) {
      back.value = defaults.return;
    }
    if (ptime && !ptime.value && defaults.ptime) {
      ptime.value = defaults.ptime;
    }
    if (rtime && !rtime.value && defaults.rtime) {
      rtime.value = defaults.rtime;
    }

    let pickupPicker = null;
    let returnPicker = null;
    let syncingDates = false;

    function showDateHint(kind) {
      if (!dateHint) {
        return;
      }
      const strong = dateHint.querySelector("strong");
      const kicker = dateHint.querySelector(".banner-dates-hint__kicker");
      if (kind === "lead") {
        if (kicker) {
          kicker.textContent = Drupal.t("Pickup");
        }
        if (strong) {
          strong.textContent = Drupal.t("Your reservation must be made at least 24 hours before the pickup date.");
        }
        dateHint.hidden = false;
      }
      else if (kind === "return") {
        if (kicker) {
          kicker.textContent = Drupal.t("Return");
        }
        if (strong) {
          strong.textContent = Drupal.t("Choose a return date on or after pickup.");
        }
        dateHint.hidden = false;
      }
      else {
        dateHint.hidden = true;
      }
    }

    function disableHoursBefore(select, minInstant, dateStr) {
      if (!select || !dateStr) {
        return;
      }
      Array.prototype.forEach.call(select.options, function (option) {
        const instant = combineDateTime(dateStr, option.value);
        option.disabled = !!(minInstant && instant && instant < minInstant);
      });
      if (select.selectedOptions[0] && select.selectedOptions[0].disabled) {
        const next = Array.prototype.find.call(select.options, function (option) {
          return !option.disabled && option.value;
        });
        select.value = next ? next.value : "";
      }
    }

    function syncHourOptions() {
      disableHoursBefore(ptime, earliestPickupDate(), pickup.value);
      disableHoursBefore(rtime, minReturnDate(pickup.value, ptime ? ptime.value : ""), back.value);
      refreshTimeDrop(ptime);
      refreshTimeDrop(rtime);
    }

    function earliestDay() {
      return isoDate(earliestPickupDate());
    }

    function returnMinDay() {
      const minInstant = minReturnDate(pickup.value, ptime ? ptime.value : "") || earliestPickupDate();
      return isoDate(minInstant);
    }

    function clearReturn() {
      syncingDates = true;
      if (returnPicker) {
        returnPicker.clear();
      }
      else {
        back.value = "";
      }
      syncingDates = false;
    }

    function syncReturnConstraint(clearInvalid) {
      const minDay = returnMinDay();
      if (returnPicker) {
        returnPicker.set("minDate", minDay);
      }
      else {
        back.min = minDay;
      }
      syncHourOptions();
      if (clearInvalid && back.value) {
        const issue = tripIssueFromValues(
          pickup.value,
          ptime ? ptime.value : "",
          back.value,
          rtime ? rtime.value : "",
        );
        if (issue === "return") {
          clearReturn();
          showDateHint("return");
          back.focus();
        }
      }
    }

    function applyDefaultWindow() {
      const windowDefaults = searchSettings().defaultWindow || {};
      syncingDates = true;
      if (pickupPicker) {
        pickupPicker.setDate(windowDefaults.pickup || earliestDay(), false);
      }
      else {
        pickup.value = windowDefaults.pickup || earliestDay();
      }
      if (returnPicker) {
        returnPicker.setDate(windowDefaults.return || "", false);
      }
      else {
        back.value = windowDefaults.return || "";
      }
      if (ptime) {
        ptime.value = windowDefaults.ptime || "10:00";
      }
      if (rtime) {
        rtime.value = windowDefaults.rtime || "10:00";
      }
      syncingDates = false;
      syncReturnConstraint(false);
      showDateHint("");
    }

    const pickerOptions = {
      dateFormat: "Y-m-d",
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      static: true,
      monthSelectorType: "static",
      nextArrow: "<span aria-hidden=\"true\">›</span>",
      prevArrow: "<span aria-hidden=\"true\">‹</span>",
      onOpen: function () {
        closeTimeDrops();
      },
    };

    if (typeof window.flatpickr === "function") {
      pickupPicker = window.flatpickr(pickup, Object.assign({}, pickerOptions, {
        minDate: earliestDay(),
        defaultDate: pickup.value || null,
        onChange: function () {
          if (syncingDates) {
            return;
          }
          syncReturnConstraint(true);
          const issue = tripIssueFromValues(
            pickup.value,
            ptime ? ptime.value : "",
            back.value,
            rtime ? rtime.value : "",
          );
          if (issue === "lead") {
            showDateHint("lead");
          }
          else if (issue !== "return") {
            showDateHint("");
          }
        },
      }));
      returnPicker = window.flatpickr(back, Object.assign({}, pickerOptions, {
        minDate: returnMinDay(),
        defaultDate: back.value || null,
        onChange: function () {
          if (syncingDates) {
            return;
          }
          syncHourOptions();
          const issue = tripIssueFromValues(
            pickup.value,
            ptime ? ptime.value : "",
            back.value,
            rtime ? rtime.value : "",
          );
          showDateHint(issue === "lead" ? "lead" : issue === "return" ? "return" : "");
        },
      }));
    }
    else {
      pickup.min = earliestDay();
      back.min = returnMinDay();
    }

    syncReturnConstraint(true);
    bindTimeDrop(ptime);
    bindTimeDrop(rtime);
    document.addEventListener("mousedown", function (event) {
      if (!event.target.closest(".lm-time-drop")) {
        closeTimeDrops();
      }
    });

    if (ptime) {
      ptime.addEventListener("change", function () {
        refreshTimeDrop(ptime);
        syncReturnConstraint(true);
      });
    }
    if (rtime) {
      rtime.addEventListener("change", function () {
        refreshTimeDrop(rtime);
        const issue = tripIssueFromValues(
          pickup.value,
          ptime ? ptime.value : "",
          back.value,
          rtime ? rtime.value : "",
        );
        showDateHint(issue === "lead" ? "lead" : issue === "return" ? "return" : "");
      });
    }

    function syncIds() {
      const pickupId = termId(from.value);
      if (pickupId) {
        fromId.value = pickupId;
        from.value = labelOnly(from.value);
      }
      const delivery = termId(to.value);
      if (delivery) {
        toId.value = delivery;
        to.value = labelOnly(to.value);
      }
      else if (!toId.value) {
        toId.value = fromId.value;
      }
    }

    function hotelNeeded() {
      return requiresPlace.includes(Number(fromId.value))
        || requiresPlace.includes(Number(toId.value));
    }

    function syncNote(clearPlace) {
      const show = hotelNeeded();
      note.hidden = !show;
      place.required = show;
      if (!show && clearPlace) {
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

    function formatSlipDate(value) {
      if (!value) {
        return "—";
      }
      const parts = String(value).split("-");
      if (parts.length !== 3) {
        return value;
      }
      const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
      if (Number.isNaN(date.getTime())) {
        return value;
      }
      return date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
    }

    function tripSummary() {
      const pickupLabel = from.value.trim() || Drupal.t("Pickup");
      const dropLabel = to.value.trim() || pickupLabel;
      const placeNote = note && !note.hidden && place && place.value.trim()
        ? " · " + place.value.trim()
        : "";
      return pickupLabel + " → " + dropLabel + placeNote + " · " + formatSlipDate(pickup.value) + "–" + formatSlipDate(back.value);
    }

    function refreshSummary() {
      if (summary) {
        summary.textContent = tripSummary();
      }
    }

    function setCollapsed(collapsed, persist) {
      if (!form.hasAttribute("data-banner-fleet") || !collapse || !panel) {
        return;
      }
      if (form.classList.contains("is-need-trip")) {
        collapsed = false;
      }
      form.classList.toggle("is-collapsed", collapsed);
      panel.hidden = collapsed;
      if (collapsed) {
        panel.setAttribute("inert", "");
      }
      else {
        panel.removeAttribute("inert");
      }
      collapse.setAttribute("aria-expanded", collapsed ? "false" : "true");
      if (collapseLabel) {
        collapseLabel.textContent = collapsed ? Drupal.t("Edit trip") : Drupal.t("Reduce");
      }
      refreshSummary();
      if (persist) {
        try {
          sessionStorage.setItem(storageKey, collapsed ? "collapsed" : "open");
        }
        catch (error) {
          // Private mode can block sessionStorage.
        }
      }
    }

    function bindDock() {
      if (!form.hasAttribute("data-banner-fleet") || !collapse || !panel) {
        return;
      }
      let stored = "";
      try {
        stored = sessionStorage.getItem(storageKey) || "";
      }
      catch (error) {
        stored = "";
      }
      const narrow = window.matchMedia("(max-width: 900px)").matches;
      const startCollapsed = stored === "collapsed" || (stored === "" && narrow);
      setCollapsed(startCollapsed, false);

      collapse.addEventListener("click", function () {
        setCollapsed(!form.classList.contains("is-collapsed"), true);
      });
      const desk = form.querySelector(".banner-desk");
      if (desk) {
        desk.addEventListener("click", function (event) {
          if (!form.classList.contains("is-collapsed") || event.target.closest("[data-banner-collapse]")) {
            return;
          }
          setCollapsed(false, true);
        });
      }
      ["input", "change"].forEach(function (eventName) {
        form.addEventListener(eventName, refreshSummary);
      });
      form.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !form.classList.contains("is-collapsed")) {
          setCollapsed(true, true);
          collapse.focus();
        }
      });
    }

    ["change", "blur", "autocompleteclose", "autocompleteselect"].forEach(function (eventName) {
      from.addEventListener(eventName, function () {
        syncIds();
        syncNote(true);
      });
      to.addEventListener(eventName, function () {
        syncIds();
        syncNote(true);
      });
    });
    syncIds();
    syncNote(false);
    bindDock();
    bindAvailabilityDraft(form);

    if (form.classList.contains("is-need-trip")) {
      const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      form.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "center" });
      if (from && !fromId.value) {
        from.focus();
      }
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
        applyDefaultWindow();
        setBusy(false);
        syncNote();
      });
    }

    form.addEventListener("submit", function (event) {
      syncIds();
      if (!fromId.value) {
        event.preventDefault();
        promptTrip(form, from);
        return;
      }
      if (hotelNeeded() && place && !place.value.trim()) {
        event.preventDefault();
        promptTrip(form, place);
        return;
      }
      if (!pickup.value && defaults.pickup) {
        if (pickupPicker) {
          pickupPicker.setDate(defaults.pickup, false);
        }
        else {
          pickup.value = defaults.pickup;
        }
      }
      if (!back.value && defaults.return) {
        if (returnPicker) {
          returnPicker.setDate(defaults.return, false);
        }
        else {
          back.value = defaults.return;
        }
      }
      const issue = tripIssueFromValues(
        pickup.value,
        ptime ? ptime.value : "",
        back.value,
        rtime ? rtime.value : "",
      );
      if (issue) {
        event.preventDefault();
        showDateHint(issue);
        if (issue === "lead") {
          pickup.focus();
        }
        else {
          back.focus();
        }
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
      showFleetSkeleton({ scroll: true });
    });
  }

  function fleetView() {
    return document.querySelector("#fleet.view-vehicle-fleet");
  }

  function showFleetSkeleton(options) {
    const view = fleetView();
    if (!view) {
      return;
    }
    const grid = view.querySelector("[data-fleet-grid]");
    const empty = view.querySelector("[data-fleet-empty]");
    const skeleton = view.querySelector("[data-fleet-skeleton]");
    const pager = view.querySelector(".fleet-pager");
    const progress = document.querySelector("[data-search-progress]");
    if (!skeleton) {
      return;
    }
    const available = skeleton.querySelectorAll(".card").length;
    const current = grid ? grid.querySelectorAll(".card").length : 0;
    const count = Math.min(available, Math.max(3, current || available));
    skeleton.querySelectorAll(".card").forEach(function (card, index) {
      card.hidden = index >= count;
    });
    skeleton.hidden = false;
    if (grid) {
      grid.hidden = true;
    }
    if (empty) {
      empty.hidden = true;
    }
    if (pager) {
      pager.hidden = true;
    }
    view.setAttribute("aria-busy", "true");
    if (progress) {
      progress.hidden = false;
    }
    if (options && options.scroll) {
      const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      view.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
    }
  }

  function bindRefine(form) {
    form.addEventListener("submit", function () {
      form.querySelectorAll("select").forEach(function (element) {
        if (!element.value) {
          element.disabled = true;
        }
      });
      showFleetSkeleton();
    });
  }

  function bindFleetPills(view) {
    once("lm-fleet-pills", ".pills a.pill", view).forEach(function (pill) {
      pill.addEventListener("click", function (event) {
        if (
          event.defaultPrevented
          || event.metaKey
          || event.ctrlKey
          || event.shiftKey
          || event.altKey
          || event.button !== 0
        ) {
          return;
        }
        if (pill.classList.contains("is-active")) {
          return;
        }
        showFleetSkeleton();
      });
    });
  }

  function promptTrip(form, focusEl) {
    form.classList.add("is-need-trip");
    const collapse = form.querySelector("[data-banner-collapse]");
    const panel = form.querySelector("[data-banner-panel]");
    if (form.hasAttribute("data-banner-fleet") && panel) {
      form.classList.remove("is-collapsed");
      panel.hidden = false;
      panel.removeAttribute("inert");
      if (collapse) {
        collapse.setAttribute("aria-expanded", "true");
      }
    }
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    form.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "center" });
    if (focusEl && typeof focusEl.focus === "function") {
      focusEl.focus();
    }
  }

  function committedWindow() {
    const settings = drupalSettings.lmVehicleSearch || {};
    return settings.committed || {};
  }

  function formWindow(form) {
    const pickup = form.querySelector("#banner-pickup");
    const back = form.querySelector("#banner-return");
    const ptime = form.querySelector("#banner-ptime");
    const rtime = form.querySelector("#banner-rtime");
    return {
      pickup: pickup ? pickup.value : "",
      return: back ? back.value : "",
      ptime: ptime ? ptime.value : "",
      rtime: rtime ? rtime.value : "",
    };
  }

  function isAvailabilityDirty(form) {
    const committed = committedWindow();
    const current = formWindow(form);
    const datesChanged = ["pickup", "return"].some(function (key) {
      return String(committed[key] || "") !== String(current[key] || "");
    });
    const timesChanged = ["ptime", "rtime"].some(function (key) {
      return String(committed[key] || "10:00") !== String(current[key] || "10:00");
    });
    return datesChanged || timesChanged;
  }

  function setFleetStale(stale) {
    const results = document.querySelector("[data-fleet-results]");
    if (!results) {
      return;
    }
    results.classList.toggle("is-stale", stale);
    const message = results.querySelector("[data-fleet-stale]");
    if (message) {
      message.hidden = !stale;
    }
    document.querySelectorAll("[data-lm-reserve]").forEach(function (link) {
      link.classList.toggle("is-disabled", stale);
      link.setAttribute("aria-disabled", stale ? "true" : "false");
      if (stale) {
        link.setAttribute("tabindex", "-1");
      }
      else {
        link.removeAttribute("tabindex");
      }
    });
  }

  function showReserveError(text) {
    const error = document.querySelector("[data-fleet-reserve-error]");
    if (!error) {
      return;
    }
    error.textContent = text || "";
    error.hidden = !text;
    error.classList.toggle("is-visible", !!text);
  }

  function bindAvailabilityDraft(form) {
    const watch = ["#banner-pickup", "#banner-return", "#banner-ptime", "#banner-rtime"];
    function refresh() {
      setFleetStale(isAvailabilityDirty(form));
      if (!isAvailabilityDirty(form)) {
        showReserveError("");
      }
    }
    watch.forEach(function (selector) {
      const element = form.querySelector(selector);
      if (!element) {
        return;
      }
      ["change", "input"].forEach(function (eventName) {
        element.addEventListener(eventName, refresh);
      });
    });
    refresh();
  }

  function bannerFields(form) {
    return {
      from: form.querySelector("#banner-from"),
      fromId: form.querySelector("#banner-from-id") || form.querySelector('input[name="from"]'),
      to: form.querySelector("#banner-to"),
      toId: form.querySelector("#banner-to-id") || form.querySelector('input[name="to"]'),
      place: form.querySelector("#banner-place"),
    };
  }

  function syncLocationIds(form) {
    const fields = bannerFields(form);
    if (!fields.from || !fields.fromId || !fields.to || !fields.toId) {
      return fields;
    }
    const pickupId = termId(fields.from.value);
    if (pickupId) {
      fields.fromId.value = pickupId;
      fields.from.value = labelOnly(fields.from.value);
    }
    const delivery = termId(fields.to.value);
    if (delivery) {
      fields.toId.value = delivery;
      fields.to.value = labelOnly(fields.to.value);
    }
    else if (!fields.toId.value && fields.fromId.value) {
      fields.toId.value = fields.fromId.value;
    }
    return fields;
  }

  function missingTripControl(form) {
    const settings = drupalSettings.lmVehicleSearch || {};
    const requiresPlace = (settings.requiresPlace || []).map(Number);
    const fields = syncLocationIds(form);
    if (!fields.fromId || !fields.fromId.value) {
      return fields.from;
    }
    const hotel = requiresPlace.includes(Number(fields.fromId.value))
      || (fields.toId && requiresPlace.includes(Number(fields.toId.value)));
    if (hotel && fields.place && !fields.place.value.trim()) {
      return fields.place;
    }
    return null;
  }

  function tripPayload(link, form) {
    const settings = drupalSettings.lmVehicleSearch || {};
    const committed = committedWindow();
    const locations = settings.locations || {};
    const fields = form ? syncLocationIds(form) : {};
    const params = new URLSearchParams(window.location.search);
    return {
      vehicle: link.getAttribute("data-lm-vehicle") || "",
      from: (fields.fromId && fields.fromId.value) || locations.from || "",
      to: (fields.toId && fields.toId.value) || locations.to || "",
      place: (fields.place && fields.place.value.trim()) || locations.place || "",
      pickup: committed.pickup || "",
      return: committed.return || "",
      ptime: committed.ptime || "10:00",
      rtime: committed.rtime || "10:00",
      category: params.get("category") || locations.category || "",
    };
  }

  function startCheckout(link, form) {
    const settings = drupalSettings.lmVehicleSearch || {};
    const startUrl = settings.startUrl || "/checkout/start";
    showReserveError("");
    link.setAttribute("aria-busy", "true");
    fetch("/session/token", { credentials: "same-origin" })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("csrf");
        }
        return response.text();
      })
      .then(function (token) {
        return fetch(startUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-Token": token,
          },
          body: JSON.stringify(tripPayload(link, form)),
        });
      })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      })
      .then(function (result) {
        if (result.ok && result.data && result.data.redirect) {
          window.location.assign(result.data.redirect);
          return;
        }
        showReserveError(
          (result.data && result.data.message) || Drupal.t("This vehicle is no longer available for the selected dates. Please choose another vehicle."),
        );
      })
      .catch(function () {
        showReserveError(Drupal.t("This vehicle is no longer available for the selected dates. Please choose another vehicle."));
      })
      .finally(function () {
        link.removeAttribute("aria-busy");
      });
  }

  function bindReserveGate(context) {
    once("lm-reserve-gate", "[data-lm-reserve]", context).forEach(function (link) {
      link.addEventListener("click", function (event) {
        if (
          event.defaultPrevented
          || event.metaKey
          || event.ctrlKey
          || event.shiftKey
          || event.altKey
          || event.button !== 0
        ) {
          return;
        }
        const form = document.querySelector("[data-banner]");
        if (form) {
          const windowValues = formWindow(form);
          const issue = tripIssueFromValues(
            windowValues.pickup,
            windowValues.ptime,
            windowValues.return,
            windowValues.rtime,
          );
          if (issue) {
            event.preventDefault();
            const hint = form.querySelector("[data-banner-date-hint]");
            if (hint) {
              const strong = hint.querySelector("strong");
              const kicker = hint.querySelector(".banner-dates-hint__kicker");
              if (kicker) {
                kicker.textContent = issue === "lead" ? Drupal.t("Pickup") : Drupal.t("Return");
              }
              if (strong) {
                strong.textContent = issue === "lead"
                  ? Drupal.t("Your reservation must be made at least 24 hours before the pickup date.")
                  : Drupal.t("Choose a return date on or after pickup.");
              }
              hint.hidden = false;
            }
            promptTrip(form, form.querySelector(issue === "lead" ? "#banner-pickup" : "#banner-return"));
            return;
          }
        }
        if (form && isAvailabilityDirty(form)) {
          event.preventDefault();
          setFleetStale(true);
          promptTrip(form, form.querySelector("#banner-pickup") || form.querySelector("[data-banner-submit]"));
          return;
        }
        if (form) {
          const missing = missingTripControl(form);
          if (missing) {
            event.preventDefault();
            promptTrip(form, missing);
            return;
          }
        }
        event.preventDefault();
        startCheckout(link, form);
      });
    });
  }

  Drupal.behaviors.lmVehicleSearch = {
    attach: function (context) {
      once("lm-vehicle-search", "[data-banner]", context).forEach(bindBanner);
      once("lm-vehicle-refine", ".fleet-refine", context).forEach(bindRefine);
      once("lm-fleet-filters", "#fleet.view-vehicle-fleet", context).forEach(bindFleetPills);
      bindReserveGate(context);
    },
  };
})(Drupal, once, drupalSettings);
