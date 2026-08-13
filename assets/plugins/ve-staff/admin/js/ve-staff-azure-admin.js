(function () {
	'use strict';
	const form = document.getElementById('ve-azure-settings');
	if (!form) return;
	const request = async (action, values) => {
		const body = new URLSearchParams(Object.assign({ action, nonce: veStaffAzure.nonce }, values));
		const response = await fetch(veStaffAzure.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
		const data = await response.json();
		if (!data.success) throw new Error(data.data && data.data.message ? data.data.message : 'The request failed.');
		return data.data;
	};
	const validateRules = (textarea) => {
		const status = textarea.parentElement.querySelector('.ve-azure-json-status');
		try { const parsed = JSON.parse(textarea.value); if (!Array.isArray(parsed)) throw new Error('Rules must be an array'); status.textContent = 'Valid JSON'; status.className = 've-azure-json-status valid'; textarea.setCustomValidity(''); }
		catch (error) { status.textContent = error.message; status.className = 've-azure-json-status invalid'; textarea.setCustomValidity(error.message); }
	};
	form.addEventListener('input', (event) => { if (event.target.matches('.ve-azure-rules')) validateRules(event.target); });
	document.querySelectorAll('.ve-azure-rules').forEach(validateRules);
	let mappingIndex = document.querySelector('#ve-azure-mappings tbody').children.length;
	document.getElementById('ve-azure-add-mapping').addEventListener('click', () => {
		const tbody = document.querySelector('#ve-azure-mappings tbody'); const index = mappingIndex; mappingIndex += 1; const row = document.createElement('tr');
		row.innerHTML = `<td><input required name="ve_staff_azure_settings[mappings][${index}][azure_field]" placeholder="mobilePhone"></td><td><input required name="ve_staff_azure_settings[mappings][${index}][target]" placeholder="group.field"></td><td><select name="ve_staff_azure_settings[mappings][${index}][direction]"><option value="azure_to_wp">Azure → WordPress</option><option value="wp_to_azure">WordPress → Azure</option><option value="disabled">Disabled</option></select></td><td><textarea required class="large-text code ve-azure-rules" rows="2" name="ve_staff_azure_settings[mappings][${index}][rules]">[]</textarea><span class="ve-azure-json-status valid">Valid JSON</span></td><td><button type="button" class="button-link-delete ve-azure-remove">Remove</button></td>`; tbody.appendChild(row);
	});
	document.getElementById('ve-azure-mappings').addEventListener('click', (event) => { if (event.target.matches('.ve-azure-remove')) event.target.closest('tr').remove(); });
	document.getElementById('ve-azure-test-connection').addEventListener('click', async () => {
		const output = document.getElementById('ve-azure-connection-result'); output.textContent = 'Testing…'; output.className = '';
		try { const data = await request('ve_staff_azure_connection_test', { tenant_id: document.getElementById('azure-tenant_id').value, client_id: document.getElementById('azure-client_id').value, client_secret: document.getElementById('azure-client_secret').value }); output.textContent = data.message; output.className = 'success'; }
		catch (error) { output.textContent = error.message; output.className = 'error'; }
	});
	document.getElementById('ve-azure-run-preview').addEventListener('click', async () => {
		const output = document.getElementById('ve-azure-preview'); const postId = document.getElementById('ve-azure-test-post').value; if (!postId) { output.textContent = 'Choose a staff post.'; return; } output.textContent = 'Loading Azure data…';
		try { const data = await request('ve_staff_azure_sync_preview', { post_id: postId }); const rows = data.rows.map((row) => `<tr><td>${escapeHtml(row.field)}</td><td>${escapeHtml(row.target)}</td><td>${escapeHtml(row.source)}</td><td><code>${escapeHtml(JSON.stringify(row.source_value))}</code></td><td><code>${escapeHtml(JSON.stringify(row.destination_value))}</code></td><td class="${row.action === 'Would update' ? 'change' : ''}">${escapeHtml(row.action)}</td></tr>`).join(''); output.innerHTML = `<p class="success">${escapeHtml(data.message)}</p><table class="widefat striped"><thead><tr><th>Azure field</th><th>WP target</th><th>Source</th><th>Source value</th><th>Current destination</th><th>Result</th></tr></thead><tbody>${rows}</tbody></table>`; }
		catch (error) { output.innerHTML = `<p class="error">${escapeHtml(error.message)}</p>`; }
	});
	const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
}());
