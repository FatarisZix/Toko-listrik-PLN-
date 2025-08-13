function toggleDropdown() {
    var dropdown = document.getElementById("dropdownMenu");
    dropdown.classList.toggle("show");
}

function calculateKwh() {
    var meterStart = parseInt(document.getElementById('meterStart').value) || 0;
    var meterEnd = parseInt(document.getElementById('meterEnd').value) || 0;
    var usage = meterEnd - meterStart;
    
    if (meterStart > 0 && meterEnd > 0) {
        if (usage >= 0) {
            document.getElementById('kwhUsage').value = usage + ' kWh';
            document.getElementById('kwhUsage').style.color = '#28a745';
        } else {
            document.getElementById('kwhUsage').value = 'Error: Meter akhir harus > meter awal';
            document.getElementById('kwhUsage').style.color = '#dc3545';
        }
    } else {
        document.getElementById('kwhUsage').value = '';
    }
}

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.dropdown-toggle')) {
        var dropdown = document.getElementById("dropdownMenu");
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
}