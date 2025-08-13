document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.querySelector("select[name='customer_id']");
    const periodInput = document.querySelector("input[name='period_month']");
    const kwhInput = document.querySelector("input[name='kwh_usage']");

    customerSelect.addEventListener('change', function() {
        const customerId = this.value;
        if (!customerId) {
            periodInput.value = '';
            kwhInput.value = '';
            return;
        }
        fetch(`get_usage_record.php?customer_id=${customerId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    periodInput.value = data.data.period_month;
                    kwhInput.value = data.data.kwh_usage;
                } else {
                    periodInput.value = '';
                    kwhInput.value = '';
                    alert(data.error);
                }
            })
            .catch(() => {
                periodInput.value = '';
                kwhInput.value = '';
                alert('Gagal mengambil data laporan meter!');
            });
    });
});