// Interaksi halaman tanpa library tambahan, sehingga tetap bisa dipakai offline.
document.addEventListener('DOMContentLoaded', () => {
  // Tampilkan konfirmasi sebelum formulir tertentu dikirim.
  document.addEventListener('submit', event => {
    const message = event.target.dataset.confirm;

    if (message && !confirm(message)) {
      event.preventDefault();
    }
  });

  // Saring baris tabel sesuai kata kunci dan filter tambahan yang dipilih.
  const applyTableFilters = tableSelector => {
    const table = document.querySelector(tableSelector);
    if (!table) {
      return;
    }

    const searchInput = [...document.querySelectorAll('[data-filter]')]
      .find(input => input.dataset.filter === tableSelector);
    const rowFilter = [...document.querySelectorAll('[data-row-filter]')]
      .find(filter => filter.dataset.rowFilter === tableSelector);
    const searchText = searchInput?.value.toLowerCase() ?? '';

    table.querySelectorAll('tbody tr').forEach(row => {
      const matchesSearch = row.textContent.toLowerCase().includes(searchText);
      const matchesFilter = !rowFilter
        || rowFilter.value === 'all'
        || row.dataset.grade === rowFilter.value;

      row.hidden = !matchesSearch || !matchesFilter;
    });
  };

  document.querySelectorAll('[data-filter]').forEach(searchInput => {
    searchInput.addEventListener('input', () => {
      applyTableFilters(searchInput.dataset.filter);
    });
  });

  document.querySelectorAll('[data-row-filter]').forEach(rowFilter => {
    rowFilter.addEventListener('change', () => {
      applyTableFilters(rowFilter.dataset.rowFilter);
    });
  });

  // Isi modal untuk menambah atau mengubah data induk.
  const masterForm = document.getElementById('formMaster');
  if (masterForm) {
    const modalTitle = document.getElementById('modalTitle');
    const baseTitle = modalTitle.textContent;

    document.querySelectorAll('[data-add]').forEach(addButton => {
      addButton.addEventListener('click', () => {
        masterForm.reset();
        masterForm.elements._id.value = '';
        modalTitle.textContent = 'Tambah ' + baseTitle;
      });
    });

    document.querySelectorAll('[data-edit]').forEach(editButton => {
      editButton.addEventListener('click', () => {
        const rowData = JSON.parse(editButton.dataset.edit);
        masterForm.reset();
        masterForm.elements._id.value = rowData[masterForm.dataset.pk];
        modalTitle.textContent = 'Ubah ' + baseTitle;

        [...masterForm.elements].forEach(field => {
          const isSystemField = ['csrf', '_id'].includes(field.name);
          const hasNoValue = !field.name || !(field.name in rowData);
          const shouldSkip = isSystemField
            || hasNoValue
            || field.type === 'hidden'
            || field.type === 'file'
            || field.type === 'password';

          if (shouldSkip) {
            return;
          }

          if (field.type === 'checkbox') {
            field.checked = rowData[field.name] == 1;
          } else {
            field.value = rowData[field.name] ?? '';
          }
        });
      });
    });
  }

  // Tambah dan hapus baris barang pada formulir peminjaman.
  const itemList = document.getElementById('items');
  const itemTemplate = document.getElementById('tplItem');

  if (!itemList || !itemTemplate) {
    return;
  }

  let itemIndex = 0;
  const addItemRow = () => {
    const newRow = itemTemplate.innerHTML.replaceAll('__i__', itemIndex);
    itemList.insertAdjacentHTML('beforeend', newRow);
    itemIndex++;
  };

  addItemRow();
  document.getElementById('addItem').addEventListener('click', addItemRow);

  itemList.addEventListener('click', event => {
    const removeButton = event.target.closest('.rm-item');

    if (removeButton && itemList.children.length > 1) {
      removeButton.closest('.item-row').remove();
    }
  });

  // Batasi jumlah barang sesuai stok yang tersedia.
  itemList.addEventListener('change', event => {
    if (!event.target.matches('select')) {
      return;
    }

    const quantityInput = event.target.closest('.item-row').querySelector('input');
    quantityInput.max = event.target.selectedOptions[0].dataset.max || '';

    if (quantityInput.max && +quantityInput.value > +quantityInput.max) {
      quantityInput.value = quantityInput.max;
    }
  });
});
