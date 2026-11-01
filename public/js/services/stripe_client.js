// Stripe Payment Element (replaces the legacy Card Element)
// The page provides the PaymentIntent client secret and the publishable key on the #submit button.
var submitButton = document.querySelector("#submit");
var clientSecret = submitButton.getAttribute('data-secret') || '';
var publicKey = submitButton.getAttribute('public-key') || '';
var stripe = Stripe(publicKey);
var elements = null;

var showMessage = function (text) {
  var message = document.querySelector("#payment-message");
  message.textContent = text;
};

var loading = function (isLoading) {
  submitButton.disabled = isLoading;
  document.querySelector("#spinner").classList.toggle("hidden", !isLoading);
  document.querySelector("#button-text").classList.toggle("hidden", isLoading);
};

var orderComplete = function () {
  document.querySelector(".result-message").classList.remove("hidden");
  document.querySelector("#payment-element").classList.add("hidden");
  submitButton.classList.add("hidden");
};

// Back from a redirect (3D Secure, bank redirect...): Stripe appends payment_intent_client_secret to the return_url
var returnSecret = new URLSearchParams(window.location.search).get("payment_intent_client_secret");
if (returnSecret) {
  stripe.retrievePaymentIntent(returnSecret).then(function (result) {
    var status = result.paymentIntent ? result.paymentIntent.status : '';
    if (status === "succeeded") { orderComplete(); }
    else if (status === "processing") { showMessage("Your payment is processing."); }
    else { showMessage("Your payment was not successful, please try again."); }
  });
}

// Stripe draws its fields in an iframe: pass it the page's tokens so they follow the design style
var token = function (name, fallback) {
  var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
};
var appearance = {
  theme: 'stripe',
  variables: {
    colorPrimary: token('--cq-color-primary', '#000000'),
    colorText: token('--cq-color-text', '#171a20'),
    colorDanger: token('--design-error', '#b3261e'),
    borderRadius: token('--cq-radius-control', '4px'),
    fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
  }
};

if (clientSecret) {
  elements = stripe.elements({
    clientSecret: clientSecret,
    appearance: appearance,
    fonts: [{ cssSrc: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap' }]
  });
  var paymentElement = elements.create("payment", { layout: "tabs" });
  paymentElement.mount("#payment-element");
} else {
  submitButton.disabled = true;
}

document.getElementById("payment-form").addEventListener("submit", function (event) {
  event.preventDefault();
  if (!elements) { return; }
  loading(true);
  stripe.confirmPayment({
    elements: elements,
    // Stripe redirects here only when the payment method needs it (3D Secure, bank redirects...)
    confirmParams: { return_url: window.location.href.split('?')[0] },
    redirect: "if_required"
  }).then(function (result) {
    loading(false);
    if (result.error) {
      showMessage(result.error.message);
    } else if (result.paymentIntent && result.paymentIntent.status === "succeeded") {
      orderComplete();
    } else {
      showMessage("Payment status: " + (result.paymentIntent ? result.paymentIntent.status : "unknown"));
    }
  });
});
