function togglePassword() {
  const pwd = document.getElementById("password");
  if (!pwd) return;
  pwd.type = (pwd.type === "password") ? "text" : "password";
}

document.addEventListener('DOMContentLoaded', () => {
  const passwordInput = document.getElementById('password');
  const confirmInput = document.getElementById('password_confirmation');

  const ruleLength = document.getElementById('rule-length');
  const ruleUpper = document.getElementById('rule-upper');
  const ruleLower = document.getElementById('rule-lower');
  const ruleNumber = document.getElementById('rule-number');
  const ruleSpecial = document.getElementById('rule-special');
  const ruleMatch = document.getElementById('rule-match');

  if (!passwordInput || !confirmInput) return;

  function setRuleState(element, isValid) {
    if (!element) return;
    element.classList.remove('valid', 'invalid');
    element.classList.add(isValid ? 'valid' : 'invalid');
  }

  function checkPasswordRules() {
    const password = passwordInput.value;
    const confirmPassword = confirmInput.value;

    setRuleState(ruleLength, password.length >= 12);
    setRuleState(ruleUpper, /[A-Z]/.test(password));
    setRuleState(ruleLower, /[a-z]/.test(password));
    setRuleState(ruleNumber, /[0-9]/.test(password));
    setRuleState(ruleSpecial, /[^A-Za-z0-9]/.test(password));
    setRuleState(ruleMatch, password !== '' && password === confirmPassword);
  }

  passwordInput.addEventListener('input', checkPasswordRules);
  confirmInput.addEventListener('input', checkPasswordRules);
});