(function (Drupal, once) {
  Drupal.behaviors.lmTermsAndFaqs = {
    attach: function (context) {
      once("lm-terms-tabs", "[data-lm-terms-tabs]", context).forEach(bindTabs);
    },
  };

  function bindTabs(root) {
    const section = root.closest(".lm-terms");
    if (!section) {
      return;
    }

    const tabs = root.querySelectorAll("[data-lm-terms-tab]");
    const panels = section.querySelectorAll("[data-lm-terms-panel]");
    if (tabs.length < 2 || panels.length < 2) {
      return;
    }

    section.classList.add("is-enhanced");

    function activate(name) {
      tabs.forEach(function (tab) {
        const active = tab.getAttribute("data-lm-terms-tab") === name;
        tab.classList.toggle("is-active", active);
        tab.setAttribute("aria-selected", active ? "true" : "false");
        tab.tabIndex = active ? 0 : -1;
      });
      panels.forEach(function (panel) {
        const active = panel.getAttribute("data-lm-terms-panel") === name;
        panel.hidden = !active;
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        activate(tab.getAttribute("data-lm-terms-tab"));
      });
      tab.addEventListener("keydown", function (event) {
        const keys = ["ArrowLeft", "ArrowRight", "Home", "End"];
        if (!keys.includes(event.key)) {
          return;
        }
        event.preventDefault();
        const list = Array.from(tabs);
        const index = list.indexOf(tab);
        let next = index;
        if (event.key === "ArrowRight") {
          next = (index + 1) % list.length;
        }
        if (event.key === "ArrowLeft") {
          next = (index - 1 + list.length) % list.length;
        }
        if (event.key === "Home") {
          next = 0;
        }
        if (event.key === "End") {
          next = list.length - 1;
        }
        list[next].focus();
        activate(list[next].getAttribute("data-lm-terms-tab"));
      });
    });

    activate("conditions");
  }
})(Drupal, once);
