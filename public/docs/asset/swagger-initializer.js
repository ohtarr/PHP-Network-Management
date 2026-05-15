window.onload = function() {
  //<editor-fold desc="Changeable Configuration Block">

  var OAUTH_CLIENT_ID = "915c46fe-ee91-41c7-98ab-b257b04ea7ec";
  var OAUTH_SCOPES = "openid profile email api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user";

  window.ui = SwaggerUIBundle({
    url: "/docs",
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset
    ],
    plugins: [
      SwaggerUIBundle.plugins.DownloadUrl
    ],
    layout: "StandaloneLayout",
    oauth2RedirectUrl: window.location.origin + "/docs/asset/oauth2-redirect.html",
    persistAuthorization: true,
    initOAuth: {
      clientId: OAUTH_CLIENT_ID,
      scopes: OAUTH_SCOPES,
      additionalQueryStringParams: { nonce: "swagger-ui" },
    },
  });

  // MutationObserver: whenever the auth dialog opens, force-fill the client_id input
  // using React's internal setter so the value is registered in component state
  var observer = new MutationObserver(function() {
    var input = document.querySelector('input#client_id');
    if (input && input.value !== OAUTH_CLIENT_ID) {
      var setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
      setter.call(input, OAUTH_CLIENT_ID);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  observer.observe(document.body, { childList: true, subtree: true, attributes: false });

  // Auto-select all text in parameter input fields on focus so that
  // example placeholder values (e.g. "SITE01") are immediately replaced
  // when the user starts typing — no manual deletion required.
  // setTimeout(..., 0) is required because SwaggerUI uses React-controlled
  // inputs; without the defer, React's own focus handler fires after ours
  // and resets the selection.
  document.addEventListener('focusin', function(e) {
    var el = e.target;
    if (
      (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') &&
      el.closest('#swagger-ui') &&
      !el.closest('.auth-wrapper') &&
      !el.closest('.dialog-ux')
    ) {
      setTimeout(function() { el.select(); }, 0);
    }
  });

  //</editor-fold>
};
