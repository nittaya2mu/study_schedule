/* profile.js — แก้ไขข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน */
'use strict';

(function initProfile() {
  const pf = document.getElementById('profileForm');
  const pw = document.getElementById('passwordForm');

  pf.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await API.post('api/profile.php', { action: 'update', ...formData(pf) });
      formErrors(pf, null);
      toast('บันทึกข้อมูลแล้ว', 'success');
    } catch (err) { formErrors(pf, err); toast(err.message, 'danger'); }
  });

  pw.addEventListener('submit', async e => {
    e.preventDefault();
    const d = formData(pw);
    if (d.new_password !== d.confirm) {
      formErrors(pw, { fields: { confirm: 1 } });
      return toast('รหัสผ่านยืนยันไม่ตรงกัน', 'danger');
    }
    try {
      await API.post('api/profile.php', { action: 'change_password', current_password: d.current_password, new_password: d.new_password });
      pw.reset();
      formErrors(pw, null);
      toast('เปลี่ยนรหัสผ่านแล้ว', 'success');
    } catch (err) { formErrors(pw, err); toast(err.message, 'danger'); }
  });
})();
