/**
 * Wizard Navigation and Calculations
 */
document.addEventListener('DOMContentLoaded', () => {
    const wizardForm = document.getElementById('wizardForm');
    if (!wizardForm) return;

    const steps = document.querySelectorAll('.wizard-step');
    const indicators = document.querySelectorAll('.step-indicator');
    let currentStep = 1;

    window.goToStep = (step) => {
        if (step < 1 || step > steps.length) return;
        
        // Basic validation for Step 1
        if (step > currentStep) {
            if (currentStep === 1) {
                if (!document.getElementById('project_id').value) {
                    Swal.fire('กรุณาเลือกโครงการ', '', 'warning');
                    return;
                }
            }
            if (currentStep === 2) {
                if (parseFloat(document.getElementById('total_requested').value) <= 0) {
                    Swal.fire('กรุณาเพิ่มรายการขอใช้เงิน', '', 'warning');
                    return;
                }
            }
        }

        steps.forEach(s => s.classList.add('hidden'));
        document.querySelector(`.wizard-step[data-step="${step}"]`).classList.remove('hidden');
        
        indicators.forEach(i => {
            const iStep = parseInt(i.dataset.step);
            if (iStep < step) {
                i.classList.add('bg-emerald-500', 'text-white');
                i.classList.remove('bg-blue-600', 'bg-slate-100', 'text-slate-400');
            } else if (iStep === step) {
                i.classList.add('bg-blue-600', 'text-white');
                i.classList.remove('bg-emerald-500', 'bg-slate-100', 'text-slate-400');
            } else {
                i.classList.add('bg-slate-100', 'text-slate-400');
                i.classList.remove('bg-emerald-500', 'bg-blue-600', 'text-white');
            }
        });

        currentStep = step;
        if (step === 4) renderSummary();
    };

    // --- Step 2: Items Logic ---
    window.addItem = () => {
        const tbody = document.getElementById('itemsBody');
        const index = tbody.children.length;
        const tr = document.createElement('tr');
        tr.className = 'group border-b border-slate-50';
        tr.innerHTML = `
            <td class="py-3 px-2">
                <input type="text" name="items[${index}][name]" class="w-full bg-slate-50 border-0 rounded-lg px-3 py-2 text-sm font-bold" placeholder="ชื่อรายการ" required>
            </td>
            <td class="py-3 px-2">
                <input type="number" name="items[${index}][qty]" class="qty-input w-full bg-slate-50 border-0 rounded-lg px-3 py-2 text-sm font-bold text-center" placeholder="0" required oninput="calcRow(this)">
            </td>
            <td class="py-3 px-2">
                <input type="text" name="items[${index}][unit]" class="w-full bg-slate-50 border-0 rounded-lg px-3 py-2 text-sm font-bold text-center" placeholder="หน่วย">
            </td>
            <td class="py-3 px-2">
                <input type="number" name="items[${index}][price]" class="price-input w-full bg-slate-50 border-0 rounded-lg px-3 py-2 text-sm font-bold text-right" placeholder="0.00" required oninput="calcRow(this)">
            </td>
            <td class="py-3 px-2">
                <input type="number" name="items[${index}][total]" class="total-input w-full bg-transparent border-0 px-3 py-2 text-sm font-black text-right" readonly value="0.00">
            </td>
            <td class="py-3 px-2 text-center">
                <button type="button" onclick="this.closest('tr').remove(); calcTotal();" class="text-slate-300 hover:text-rose-500 transition-all"><i class="bi bi-trash-fill"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    };

    window.calcRow = (el) => {
        const tr = el.closest('tr');
        const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
        const price = parseFloat(tr.querySelector('.price-input').value) || 0;
        tr.querySelector('.total-input').value = (qty * price).toFixed(2);
        calcTotal();
    };

    window.calcTotal = () => {
        let total = 0;
        document.querySelectorAll('.total-input').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        document.getElementById('total_requested_display').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('total_requested').value = total;

        // Check budget limit
        const limit = parseFloat(document.getElementById('budget_limit').value) || 0;
        const warning = document.getElementById('budget_warning');
        if (total > limit) {
            warning.classList.remove('hidden');
            document.getElementById('btnNext2').disabled = true;
            document.getElementById('btnNext2').classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            warning.classList.add('hidden');
            document.getElementById('btnNext2').disabled = false;
            document.getElementById('btnNext2').classList.remove('opacity-50', 'cursor-not-allowed');
        }
    };

    // --- Step 3: Committee Logic ---
    window.addComm = () => {
        const tbody = document.getElementById('commBody');
        const index = tbody.children.length;
        const tr = document.createElement('tr');
        tr.className = 'group border-b border-slate-50';
        tr.innerHTML = `
            <td class="py-3 px-2">
                <input type="text" name="comm[${index}][name]" class="w-full bg-slate-50 border-0 rounded-lg px-3 py-2 text-sm font-bold" placeholder="ชื่อ-นามสกุล" required>
            </td>
            <td class="py-3 px-2">
                <input type="text" name="comm[${index}][pos]" class="w-full bg-slate-50 border-0 rounded-lg px-3 py-2 text-sm font-bold" placeholder="ตำแหน่ง">
            </td>
            <td class="py-3 px-2">
                <select name="comm[${index}][role]" class="w-full bg-slate-50 border-0 rounded-lg px-3 py-2 text-sm font-bold">
                    <option value="กรรมการ">กรรมการ</option>
                    <option value="ประธานกรรมการ">ประธานกรรมการ</option>
                    <option value="กรรมการและเลขานุการ">กรรมการและเลขานุการ</option>
                </select>
            </td>
            <td class="py-3 px-2 text-center">
                <button type="button" onclick="this.closest('tr').remove();" class="text-slate-300 hover:text-rose-500 transition-all"><i class="bi bi-trash-fill"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    };

    // --- Step 4: Summary ---
    function renderSummary() {
        const summary = document.getElementById('summaryView');
        const projectName = document.getElementById('project_id').options[document.getElementById('project_id').selectedIndex]?.text;
        const total = document.getElementById('total_requested').value;
        const items = document.querySelectorAll('#itemsBody tr');
        
        let itemsHtml = '';
        items.forEach(tr => {
            const name = tr.querySelector('input[name*="[name]"]').value;
            const rowTotal = tr.querySelector('.total-input').value;
            itemsHtml += `
                <div class="flex justify-between text-xs font-bold py-1 border-b border-slate-50">
                    <span class="text-slate-500">${name}</span>
                    <span class="text-slate-800">${parseFloat(rowTotal).toLocaleString()}</span>
                </div>
            `;
        });

        summary.innerHTML = `
            <div class="space-y-4">
                <div class="bg-blue-50 p-4 rounded-2xl">
                    <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-1">โครงการ</p>
                    <p class="font-black text-blue-900 text-sm">${projectName}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">รายการ</p>
                    ${itemsHtml}
                </div>
                <div class="flex justify-between items-center pt-4 border-t border-slate-100">
                    <p class="font-black text-slate-800">ยอดรวมทั้งสิ้น</p>
                    <p class="text-xl font-black text-blue-600 underline">${parseFloat(total).toLocaleString()} บาท</p>
                </div>
            </div>
        `;
    }

    // Auto add first row
    addItem();
});
