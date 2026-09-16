/**
 * @file
 * Dismissible in-view status alerts, matching Drupal.Message markup.
 */
(function (Drupal, once) {
  Drupal.behaviors.lmAlerts = {
    attach: function (context) {
      once("lm-alert-dismiss", "html", context).forEach(function () {
        document.addEventListener("click", onDismissClick);
      });
    },
  };

  function onDismissClick(event) {
    const button = event.target.closest("[data-lm-alert-dismiss]");
    if (!button) {
      return;
    }
    const alert = button.closest(".messages");
    if (!alert) {
      return;
    }
    alert.classList.add("is-dismissed");
    window.setTimeout(function () {
      alert.remove();
    }, 180);
  }

  Drupal.theme.message = function ({ text }, { type, id }) {
    const labels = Drupal.Message.getMessageTypeLabels();
    const article = document.createElement("div");
    const role = type === "error" || type === "warning" ? "alert" : "status";

    article.className = "messages messages--" + type;
    article.setAttribute("role", role);
    article.setAttribute("data-drupal-message-id", id);
    article.setAttribute("data-drupal-message-type", type);
    article.setAttribute("aria-label", labels[type] || type);

    article.innerHTML =
      '<span class="messages__icon" aria-hidden="true"></span>' +
      '<div class="messages__body">' +
      '<p class="messages__kicker"></p>' +
      '<div class="messages__content"></div>' +
      "</div>" +
      '<button type="button" class="messages__dismiss" data-lm-alert-dismiss>' +
      '<span class="visually-hidden">' +
      Drupal.t("Dismiss") +
      "</span>" +
      '<span aria-hidden="true">×</span>' +
      "</button>";

    article.querySelector(".messages__kicker").textContent =
      labels[type] || type;
    article.querySelector(".messages__content").innerHTML = text;

    return article;
  };
})(Drupal, once);
