$(document).ready(function() {
  $('.pop-select').select2({ placeholder: "Select POP Name", allowClear: true });
  $('.switch-select').select2({ placeholder: "Select Switch Name", allowClear: true });
  toggleNetworkFields();
});

function updatePopIp() {
  var popSelect = document.getElementById('pop_name');
  var popIpInput = document.getElementById('pop_ip');
  var selectedOption = popSelect.options[popSelect.selectedIndex];
  popIpInput.value = selectedOption.getAttribute('data-ip') || '';
}

function updateSwitchIp() {
  var switchSelect = document.getElementById('switch_name');
  var switchIpInput = document.getElementById('switch_ip');
  var selectedOption = switchSelect.options[switchSelect.selectedIndex];
  switchIpInput.value = selectedOption.getAttribute('data-ip') || '';
}

function addIp() {
  let container = document.getElementById('ip-list');
  let div = document.createElement('div');
  div.className = 'input-group mb-1';
  div.innerHTML = `<input type="text" name="additional_ips[]" class="form-control" placeholder="Enter IP address">
                   <button type="button" class="btn btn-danger btn-sm" onclick="removeIp(this)">Remove</button>`;
  container.appendChild(div);
}

function removeIp(button) {
  button.parentElement.remove();
}

function addContactField() {
  const div = document.createElement('div');
  div.className = 'input-group mb-1';
  div.innerHTML = `<input type="text" class="form-control" name="contact_number[]" maxlength="15" required>
                   <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>`;
  document.getElementById('contacts').appendChild(div);
}
function addEmailField() {
  const div = document.createElement('div');
  div.className = 'input-group mb-1';
  div.innerHTML = `<input type="email" class="form-control" name="ce_email_id[]" maxlength="100" required>
                   <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>`;
  document.getElementById('emails').appendChild(div);
}
function removeField(btn) { btn.parentNode.remove(); }

function toggleNetworkFields() {
  const authType = document.getElementById('auth_type').value;
  const netFields = document.getElementById('network_fields');
  const wanIpField = document.getElementById('wan_ip_field');
  const staticFields = document.querySelectorAll('.static-fields');
  const pppoeFields = document.querySelectorAll('.pppoe-fields');
  const wanIpInput = document.getElementById('wan_ip');
  const netmask = document.getElementById('netmask');
  const gateway = document.getElementById('wan_gateway');
  const dns1 = document.getElementById('dns1');
  const dns2 = document.getElementById('dns2');
  const pppoeUser = document.getElementById('pppoe_username');
  const pppoePass = document.getElementById('pppoe_password');

  netFields.style.display = 'none';
  wanIpField.style.display = 'none';
  staticFields.forEach(f => f.style.display = 'none');
  pppoeFields.forEach(f => f.style.display = 'none');

  // Remove all required
  if (wanIpInput) wanIpInput.required = false;
  if (netmask) netmask.required = false;
  if (gateway) gateway.required = false;
  if (dns1) dns1.required = false;
  if (dns2) dns2.required = false;
  if (pppoeUser) pppoeUser.required = false;
  if (pppoePass) pppoePass.required = false;

  if (authType === "Static") {
    netFields.style.display = '';
    wanIpField.style.display = '';
    staticFields.forEach(f => f.style.display = '');
    if (wanIpInput) wanIpInput.required = true;
    if (netmask) netmask.required = true;
    if (gateway) gateway.required = true;
    if (dns1) dns1.required = true;
  } else if (authType === "PPPoE") {
    netFields.style.display = '';
    wanIpField.style.display = '';
    pppoeFields.forEach(f => f.style.display = '');
    if (pppoeUser) pppoeUser.required = true;
    if (pppoePass) pppoePass.required = true;
  }
}
document.getElementById('auth_type').addEventListener('change', toggleNetworkFields);