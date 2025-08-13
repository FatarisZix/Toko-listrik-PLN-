
function toggleDropdown() {
    var dropdown = document.getElementById("dropdownMenu");
    dropdown.classList.toggle("show");
}

function openModal() {
    document.getElementById("addModal").classList.add("show");
}

function closeModal() {
    document.getElementById("addModal").classList.remove("show");
}

function openRejectModal(reportId, customerName) {
    document.getElementById("reject_report_id").value = reportId;
    document.getElementById("reject_customer_name").textContent = customerName;
    document.getElementById("rejectModal").classList.add("show");
}

function closeRejectModal() {
    document.getElementById("rejectModal").classList.remove("show");
}

function calculateUsage() {
    var meterStart = parseInt(document.querySelector('input[name="meter_start"]').value) || 0;
    var meterEnd = parseInt(document.querySelector('input[name="meter_end"]').value) || 0;
    var usage = meterEnd - meterStart;
    
    if (usage >= 0) {
        document.querySelector('input[name="kwh_usage"]').value = usage;
    } else {
        alert('Meter akhir harus lebih besar dari meter awal!');
        document.querySelector('input[name="meter_end"]').value = '';
        document.querySelector('input[name="kwh_usage"]').value = '';
    }
}



// Auto calculate when meter start changes too
document.querySelector('input[name="meter_start"]').addEventListener('input', calculateUsage);

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.dropdown-toggle')) {
        var dropdown = document.getElementById("dropdownMenu");
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
    
    // Close modal when clicking outside
    var modal = document.getElementById("addModal");
    if (event.target == modal) {
        closeModal();
    }
    
    var rejectModal = document.getElementById("rejectModal");
    if (event.target == rejectModal) {
        closeRejectModal();
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.display = 'none';
    });
}, 5000);

