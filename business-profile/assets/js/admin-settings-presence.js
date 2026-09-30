/* Explicitly submit unchecked, enabled top-level settings without clearing absent tabs. */
document.addEventListener('DOMContentLoaded', function () {
	['opening-hours', 'exceptions'].forEach(function (group) {
		var schedule = document.getElementById(group);
		if (!schedule || schedule.classList.contains('disabled')) return;
		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'bpfwp-settings[' + group + ']';
		hidden.value = '';
		schedule.parentNode.insertBefore(hidden, schedule);
	});
	document.querySelectorAll('input[type="checkbox"][name]').forEach(function (input) {
		if (input.disabled || !/^bpfwp-settings\[[^\]]+\]$/.test(input.name)) return;
		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = input.name;
		hidden.value = '0';
		input.parentNode.insertBefore(hidden, input);
	});
});
