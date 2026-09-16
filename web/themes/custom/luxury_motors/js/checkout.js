(function (Drupal, once) {
  function copyText(value) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(value);
    }
    return new Promise(function (resolve, reject) {
      const field = document.createElement("textarea");
      field.value = value;
      field.setAttribute("readonly", "");
      field.style.position = "fixed";
      field.style.left = "-9999px";
      document.body.appendChild(field);
      field.select();
      try {
        document.execCommand("copy") ? resolve() : reject();
      }
      catch (error) {
        reject(error);
      }
      field.remove();
    });
  }

  function bindCopy(button) {
    const copyIcon = button.querySelector(".confirm-ref-copy__icon--copy");
    const doneIcon = button.querySelector(".confirm-ref-copy__icon--done");
    const idleLabel = button.getAttribute("aria-label") || Drupal.t("Copy confirmation code");
    let timer;

    button.addEventListener("click", function () {
      const value = button.getAttribute("data-confirm-copy") || "";
      if (!value) {
        return;
      }
      copyText(value).then(function () {
        button.classList.add("is-copied");
        button.setAttribute("aria-label", Drupal.t("Copied"));
        if (copyIcon) {
          copyIcon.hidden = true;
        }
        if (doneIcon) {
          doneIcon.hidden = false;
        }
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          button.classList.remove("is-copied");
          button.setAttribute("aria-label", idleLabel);
          if (copyIcon) {
            copyIcon.hidden = false;
          }
          if (doneIcon) {
            doneIcon.hidden = true;
          }
        }, 2000);
      });
    });
  }

  Drupal.behaviors.lmConfirmCopy = {
    attach: function (context) {
      once("lm-confirm-copy", "[data-confirm-copy]", context).forEach(bindCopy);
    },
  };
})(Drupal, once);
