/* Shipment detail actions */
(function () {
    'use strict';
    var box = document.getElementById('acts'); if (!box) return;
    var id = box.dataset.id;
    function post(body, done) { SMS.api(APP.base + '/shipments/api', { method: 'POST', body: Object.assign({ id: id }, body) }).then(function (r) { if (r.success) SMS.reloadWith(r.message, 'success'); else SMS.toast(r.message, 'danger'); }); }
    box.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-act]'); if (!b) return;
        var a = b.dataset.act;
        if (a === 'pay') return post({ action: 'pay', method: document.getElementById('payMethod').value });
        if (a === 'regen_otp') return SMS.confirmBox('Issue a new delivery code and text it to the receiver?', 'Re-issue', 'primary').then(function (ok) { if (ok) post({ action: 'regen_otp' }); });
        if (a === 'return') return SMS.confirmBox('Return this parcel to the sender? This cannot be undone.', 'Return parcel').then(function (ok) { if (ok) post({ action: 'return' }); });
        if (a === 'cancel') return SMS.confirmBox('Cancel this shipment? Any payment will be refunded.', 'Cancel shipment').then(function (ok) { if (ok) post({ action: 'cancel' }); });
    });
})();
