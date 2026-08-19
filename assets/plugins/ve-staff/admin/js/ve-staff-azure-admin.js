(function () {
	'use strict';
	const form = document.getElementById('ve-azure-settings');
	const request = async (action, values) => {
		const body = new URLSearchParams(Object.assign({ action, nonce: veStaffAzure.nonce }, values));
		const response = await fetch(veStaffAzure.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
		const data = await response.json();
		if (!data.success) throw new Error(data.data && data.data.message ? data.data.message : 'The request failed.');
		return data.data;
	};
	const termValuesButton = document.querySelector('.ve-staff-fetch-azure-values');
	if (termValuesButton) {
		termValuesButton.addEventListener('click', async () => {
			const status = document.querySelector('.ve-azure-value-status');
			const options = document.getElementById('ve-staff-azure-values');
			status.textContent = ' Loading Azure values…';
			try {
				const data = await request('ve_staff_azure_term_values', {});
				const values = data.values && data.values[veStaffAzure.taxonomy] ? data.values[veStaffAzure.taxonomy] : [];
				options.replaceChildren(...values.map((value) => new Option('', value)));
				status.textContent = values.length ? ` Loaded ${values.length} values. Select the Azure value field to choose one.` : ' No populated values were found.';
			} catch (error) { status.textContent = ` ${error.message}`; status.className = 've-azure-value-status error'; }
		});
	}
	if (!form) return;
	const validateRules = (textarea) => {
		const status = textarea.parentElement.querySelector('.ve-azure-json-status');
		try { const parsed = JSON.parse(textarea.value); if (!Array.isArray(parsed)) throw new Error('Rules must be an array'); status.textContent = 'Valid JSON'; status.className = 've-azure-json-status valid'; textarea.setCustomValidity(''); }
		catch (error) { status.textContent = error.message; status.className = 've-azure-json-status invalid'; textarea.setCustomValidity(error.message); }
	};
	const saveField = async (input) => {
		const status = input.parentElement.querySelector('.ve-azure-save-status');
		if (!input.reportValidity() || (input.dataset.setting === 'client_secret' && !input.value)) return;
		status.textContent = 'Saving…'; status.className = 've-azure-save-status saving';
		try {
			const data = await request('ve_staff_azure_save_field', { field: input.dataset.setting, value: input.value });
			status.textContent = data.message; status.className = 've-azure-save-status success';
			if (input.dataset.setting === 'client_secret') {
				input.value = '';
				if (!secretAction.querySelector('option[value="keep"]')) secretAction.add(new Option('Keep saved secret', 'keep'), 0);
				secretAction.value = 'keep'; updateSecretEntry();
			}
		} catch (error) { status.textContent = error.message; status.className = 've-azure-save-status error'; }
	};
	form.addEventListener('change', (event) => { if (event.target.matches('.ve-azure-autosave')) saveField(event.target); });
	form.addEventListener('submit', () => { form.querySelectorAll('.ve-azure-autosave').forEach((input) => { input.disabled = true; }); });
	form.addEventListener('input', (event) => { if (event.target.matches('.ve-azure-rules')) validateRules(event.target); });
	document.querySelectorAll('.ve-azure-rules').forEach(validateRules);
	const secretAction = document.getElementById('azure-client_secret_action');
	const secretEntry = document.getElementById('ve-azure-secret-entry');
	const secretInput = document.getElementById('azure-client_secret');
	const updateSecretEntry = () => { const replacing = secretAction.value === 'replace'; secretEntry.hidden = !replacing; secretInput.required = replacing; if (!replacing) secretInput.value = ''; };
	secretAction.addEventListener('change', updateSecretEntry);
	updateSecretEntry();
	let mappingIndex = document.querySelector('#ve-azure-mappings tbody').children.length;
	document.getElementById('ve-azure-add-mapping').addEventListener('click', () => {
		const tbody = document.querySelector('#ve-azure-mappings tbody'); const index = mappingIndex; mappingIndex += 1; const row = document.createElement('tr');
		row.innerHTML = `<td data-label="Azure field"><input required list="ve-azure-field-options" name="ve_staff_azure_settings[mappings][${index}][azure_field]" placeholder="mobilePhone"></td><td data-label="WordPress target"></td><td data-label="Source of truth"><select name="ve_staff_azure_settings[mappings][${index}][direction]"><option value="azure_to_wp">Azure → WordPress</option><option value="wp_to_azure">WordPress → Azure</option><option value="disabled">Disabled</option></select></td><td data-label="Rules (JSON array)"><textarea required class="large-text code ve-azure-rules" rows="2" name="ve_staff_azure_settings[mappings][${index}][rules]">[]</textarea><span class="ve-azure-json-status valid">Valid JSON</span></td><td class="ve-azure-mapping-actions"><button type="button" class="button-link-delete ve-azure-remove">Remove</button></td>`;
		const targetSelect = document.createElement('select');
		targetSelect.required = true; targetSelect.name = `ve_staff_azure_settings[mappings][${index}][target]`; targetSelect.innerHTML = `<option value="">Choose a field…</option>${veStaffAzure.targetOptions}`;
		row.children[1].appendChild(targetSelect); tbody.appendChild(row);
	});
	document.getElementById('ve-azure-mappings').addEventListener('click', (event) => { if (event.target.matches('.ve-azure-remove')) event.target.closest('tr').remove(); });
	document.getElementById('ve-azure-test-connection').addEventListener('click', async () => {
		const output = document.getElementById('ve-azure-connection-result'); output.textContent = 'Testing…'; output.className = '';
		try {
			const data = await request('ve_staff_azure_connection_test', { tenant_id: document.getElementById('azure-tenant_id').value, client_id: document.getElementById('azure-client_id').value, client_secret: document.getElementById('azure-client_secret').value }); output.textContent = data.message; output.className = 'success';
			const fields = Object.entries(data.discovered_fields || {}); const discovered = document.getElementById('ve-azure-discovered-fields'); const options = document.getElementById('ve-azure-field-options');
			fields.forEach(([name]) => { if (!Array.from(options.options).some((option) => option.value === name)) options.appendChild(new Option('', name)); });
			discovered.innerHTML = fields.length ? `<h3>Fields populated on the sampled Azure user</h3><p class="description">Select any field below in an Azure field box. Sample values are shown only to administrators and are not saved.</p><table class="widefat striped"><thead><tr><th>Azure field</th><th>Sample value</th></tr></thead><tbody>${fields.map(([name, value]) => `<tr><td><code>${escapeHtml(name)}</code></td><td>${escapeHtml(value)}</td></tr>`).join('')}</tbody></table>` : '<p>No populated user fields were returned by Microsoft Graph.</p>';
		}
		catch (error) { output.textContent = error.message; output.className = 'error'; }
	});
	document.getElementById('ve-azure-run-preview').addEventListener('click', async () => {
		const output = document.getElementById('ve-azure-preview'); const postId = document.getElementById('ve-azure-test-post').value; if (!postId) { output.textContent = 'Choose a staff post.'; return; } output.textContent = 'Loading Azure data…';
		try { const data = await request('ve_staff_azure_sync_preview', { post_id: postId }); const rows = data.rows.map((row) => `<tr><td>${escapeHtml(row.field)}</td><td>${escapeHtml(row.target)}</td><td>${escapeHtml(row.source)}</td><td><code>${escapeHtml(JSON.stringify(row.source_value))}</code></td><td><code>${escapeHtml(JSON.stringify(row.destination_value))}</code></td><td class="${row.action === 'Would update' ? 'change' : ''}">${escapeHtml(row.action)}</td></tr>`).join(''); output.innerHTML = `<p class="success">${escapeHtml(data.message)}</p><table class="widefat striped"><thead><tr><th>Azure field</th><th>WP target</th><th>Source</th><th>Source value</th><th>Current destination</th><th>Result</th></tr></thead><tbody>${rows}</tbody></table>`; }
		catch (error) { output.innerHTML = `<p class="error">${escapeHtml(error.message)}</p>`; }
	});
	const selectedPostId = () => document.getElementById('ve-azure-test-post').value;
	document.getElementById('ve-azure-view-user').addEventListener('click', async () => {
		const output = document.getElementById('ve-azure-preview'); const postId = selectedPostId(); if (!postId) { output.textContent = 'Choose a staff post.'; return; } output.textContent = 'Loading Azure data…';
		try { const data = await request('ve_staff_azure_view_user', { post_id: postId }); const rows = Object.entries(data.fields).map(([field, value]) => `<tr><td><code>${escapeHtml(field)}</code></td><td><code>${escapeHtml(value)}</code></td></tr>`).join(''); output.innerHTML = `<p class="success">Azure data for ${escapeHtml(data.employee)}</p><div class="ve-azure-table-scroll"><table class="widefat striped"><thead><tr><th>Azure field</th><th>Current Azure value</th></tr></thead><tbody>${rows}</tbody></table></div>`; }
		catch (error) { output.innerHTML = `<p class="error">${escapeHtml(error.message)}</p>`; }
	});
	document.getElementById('ve-azure-run-comparison').addEventListener('click', async () => {
		const output = document.getElementById('ve-azure-comparison'); const postIds = Array.from(document.getElementById('ve-azure-compare-posts').selectedOptions, (option) => option.value); if (!postIds.length) { output.textContent = 'Choose at least one staff post.'; return; } output.textContent = 'Loading comparison…';
		try { const values = {}; postIds.forEach((postId, index) => { values[`post_ids[${index}]`] = postId; }); const data = await request('ve_staff_azure_compare_staff', values); const rows = data.rows.map((row) => `<tr><td>${escapeHtml(row.employee)}</td><td><code>${escapeHtml(row.field)}</code><br><small>${escapeHtml(row.target)}</small></td><td><code>${escapeHtml(JSON.stringify(row.wordpress_value))}</code></td><td><code>${escapeHtml(JSON.stringify(row.azure_value))}</code></td><td class="${row.matches ? 'match' : 'change'}">${row.matches ? 'Match' : 'Different'}</td></tr>`).join(''); output.innerHTML = `<div class="ve-azure-table-scroll"><table class="widefat striped"><thead><tr><th>Employee</th><th>Mapped fields</th><th>WordPress value</th><th>Azure value</th><th>Comparison</th></tr></thead><tbody>${rows}</tbody></table></div>`; }
		catch (error) { output.innerHTML = `<p class="error">${escapeHtml(error.message)}</p>`; }
	});
	const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
}());
