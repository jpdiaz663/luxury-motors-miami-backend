/**
 * @file
 * Site-wide top progress bar for native form navigation.
 */
(function () {
  function bar() {
    return document.querySelector("[data-search-progress]");
  }

  function show() {
    const element = bar();
    if (element) {
      element.hidden = false;
    }
  }

  function hide() {
    const element = bar();
    if (element) {
      element.hidden = true;
    }
  }

  function skipForm(form) {
    if (form.target === "_blank" || form.hasAttribute("data-no-progress")) {
      return true;
    }
    return Boolean(form.closest("#toolbar-administration, .gin-secondary-toolbar, .gin-toolbar"));
  }

  document.addEventListener("submit", function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || skipForm(form)) {
      return;
    }
    show();
  });

  window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
      hide();
    }
  });
})();
