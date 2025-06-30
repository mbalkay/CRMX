<!-- JavaScript for Modern Interface -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            const targetTab = this.getAttribute('data-tab');
            document.getElementById(targetTab).classList.add('active');
        });
    });
    
    // Password confirmation validation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (password && confirmPassword) {
        function validatePasswords() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        password.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
    
    // Role-based permission defaults
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            const role = parseInt(this.value);
            const permissions = [
                'customer_edit', 'customer_delete', 'policy_edit', 'policy_delete',
                'task_edit', 'export_data', 'can_change_customer_representative',
                'can_change_policy_representative', 'can_change_task_representative',
                'can_view_deleted_policies', 'can_restore_deleted_policies'
            ];
            
            permissions.forEach(permission => {
                const checkbox = document.querySelector(`input[name="${permission}"]`);
                if (checkbox) {
                    if (role <= 2) { // Patron or Müdür
                        checkbox.checked = true;
                    } else if (role === 3) { // Müdür Yardımcısı
                        if (['customer_edit', 'policy_edit', 'task_edit'].includes(permission)) {
                            checkbox.checked = true;
                        } else {
                            checkbox.checked = false;
                        }
                    } else { // Ekip Lideri or Müşteri Temsilcisi
                        if (['customer_edit', 'policy_edit', 'task_edit'].includes(permission)) {
                            checkbox.checked = true;
                        } else {
                            checkbox.checked = false;
                        }
                    }
                }
            });
        });
    }
});

// View toggle functionality
function toggleView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location.href = url.toString();
}

// Child birthday management
function addChildRow() {
    const container = document.getElementById('children-birthdays-container');
    const rows = container.querySelectorAll('.child-birthday-row');
    const nextIndex = rows.length;
    
    const newRow = document.createElement('div');
    newRow.className = 'child-birthday-row';
    newRow.setAttribute('data-index', nextIndex);
    newRow.style.cssText = 'margin-bottom: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;';
    
    newRow.innerHTML = `
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" name="children_birthdays[${nextIndex}][name]" 
                   placeholder="Çocuğun adı" style="flex: 1; min-width: 150px;">
            <input type="date" name="children_birthdays[${nextIndex}][birth_date]" 
                   style="flex: 1;">
            <button type="button" class="remove-child-btn" onclick="removeChildRow(this)"
                    style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                <i class="dashicons dashicons-minus" style="font-size: 12px;"></i>
            </button>
        </div>
    `;
    
    container.appendChild(newRow);
}

function removeChildRow(button) {
    const container = document.getElementById('children-birthdays-container');
    const rows = container.querySelectorAll('.child-birthday-row');
    
    // Don't remove if it's the only row
    if (rows.length > 1) {
        button.closest('.child-birthday-row').remove();
        
        // Re-index remaining rows
        const remainingRows = container.querySelectorAll('.child-birthday-row');
        remainingRows.forEach((row, index) => {
            row.setAttribute('data-index', index);
            const inputs = row.querySelectorAll('input');
            inputs[0].name = `children_birthdays[${index}][name]`;
            inputs[1].name = `children_birthdays[${index}][birth_date]`;
        });
    } else {
        // Clear the inputs instead of removing the row
        const inputs = button.closest('.child-birthday-row').querySelectorAll('input');
        inputs.forEach(input => input.value = '');
    }
}
</script>