document.addEventListener('submit', event => {
  const form = event.target;
  const confirmation = form.dataset.confirm || event.submitter?.dataset.confirm;
  if (confirmation && !window.confirm(confirmation)) event.preventDefault();
});

document.querySelectorAll('.review-feedback-form').forEach(form => {
  const correction = form.querySelector('[name="correction_detail"]');
  if (!correction) return;
  form.querySelectorAll('button[name="decision"]').forEach(button => {
    button.addEventListener('click', () => {
      correction.required = button.value === 'correction_required';
    });
  });
});

document.querySelectorAll('input[type="file"][data-file-feedback]').forEach(input => {
  input.addEventListener('change', () => {
    const output = document.getElementById(input.dataset.fileFeedback);
    if (!output) return;
    output.textContent = input.files.length
      ? `${input.files[0].name} (${(input.files[0].size / 1024 / 1024).toFixed(2)} MB)`
      : '';
  });
});

const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarClose = document.querySelector('[data-sidebar-close]');
const setSidebarOpen = open => {
  document.body.classList.toggle('sidebar-open', open);
  sidebarToggle?.setAttribute('aria-expanded', String(open));
};
sidebarToggle?.addEventListener('click', () => setSidebarOpen(!document.body.classList.contains('sidebar-open')));
sidebarClose?.addEventListener('click', () => setSidebarOpen(false));
document.addEventListener('keydown', event => { if (event.key === 'Escape') setSidebarOpen(false); });

const collapseToggle = document.querySelector('[data-sidebar-collapse-toggle]');
const applySidebarCollapsed = collapsed => {
  document.body.classList.toggle('sidebar-collapsed', collapsed);
  collapseToggle?.setAttribute('aria-expanded', String(!collapsed));
  collapseToggle?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
  collapseToggle?.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
};
if (collapseToggle) {
  let savedCollapsed = false;
  try { savedCollapsed = localStorage.getItem('naub-fyp-sidebar-collapsed') === 'true'; } catch (_) {}
  applySidebarCollapsed(savedCollapsed);
  collapseToggle.addEventListener('click', () => {
    const collapsed = !document.body.classList.contains('sidebar-collapsed');
    applySidebarCollapsed(collapsed);
    try { localStorage.setItem('naub-fyp-sidebar-collapsed', String(collapsed)); } catch (_) {}
  });
}

const headerMenus = document.querySelector('[data-header-menus]');
const closeHeaderMenus = () => {
  headerMenus?.querySelectorAll('[data-menu-toggle]').forEach(button => {
    button.setAttribute('aria-expanded', 'false');
  });
  headerMenus?.querySelectorAll('[data-menu-panel]').forEach(panel => { panel.hidden = true; });
};
headerMenus?.querySelectorAll('[data-menu-toggle]').forEach(button => {
  button.addEventListener('click', () => {
    const name = button.dataset.menuToggle;
    const panel = headerMenus.querySelector(`[data-menu-panel="${name}"]`);
    const opening = panel?.hidden;
    closeHeaderMenus();
    if (opening && panel) {
      panel.hidden = false;
      button.setAttribute('aria-expanded', 'true');
    }
  });
});
document.addEventListener('pointerdown', event => {
  if (headerMenus && !headerMenus.contains(event.target)) closeHeaderMenus();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    const openButton = headerMenus?.querySelector('[data-menu-toggle][aria-expanded="true"]');
    closeHeaderMenus();
    openButton?.focus();
  }
});
document.querySelectorAll('.side-navigation a').forEach(link => {
  link.addEventListener('click', () => setSidebarOpen(false));
});

const assignmentStudent = document.getElementById('student_id');
const assignmentSupervisor = document.getElementById('supervisor_id');
if (assignmentStudent && assignmentSupervisor) {
  const filterSupervisors = () => {
    const department = assignmentStudent.selectedOptions[0]?.dataset.department || '';
    [...assignmentSupervisor.options].forEach(option => {
      if (!option.value) return;
      option.hidden = Boolean(department && option.dataset.department !== department);
      if (option.selected && option.hidden) assignmentSupervisor.value = '';
    });
  };
  assignmentStudent.addEventListener('change', filterSupervisors);
  filterSupervisors();
}

const documentType = document.getElementById('document_type');
const uploadButton = document.getElementById('upload-submit');
if (documentType && uploadButton) {
  const updateFinalEligibility = () => {
    if (documentType.value === 'final_project') {
      uploadButton.disabled = documentType.dataset.hasProject !== '1' || documentType.dataset.finalReady !== '1';
      uploadButton.title = uploadButton.disabled ? 'Chapter 5 must be approved before final submission.' : '';
    } else {
      uploadButton.disabled = documentType.dataset.hasProject !== '1';
      uploadButton.removeAttribute('title');
    }
  };
  documentType.addEventListener('change', updateFinalEligibility);
  updateFinalEligibility();
}

const submissionKind = document.querySelector('[data-submission-kind]');
if (submissionKind && documentType) {
  const kindOf = option => {
    if (option.value === 'proposal') return 'proposal';
    if (option.value === 'final_project') return 'final';
    if (option.value.startsWith('chapter_')) return 'chapter';
    return 'supporting';
  };
  if (documentType.value === 'proposal' && !new URLSearchParams(window.location.search).has('type')) {
    submissionKind.value = 'chapter';
  } else {
    submissionKind.value = kindOf(documentType.selectedOptions[0]);
  }
  const filterDocumentStages = () => {
    const available = [...documentType.options].filter(option => kindOf(option) === submissionKind.value);
    [...documentType.options].forEach(option => { option.hidden = !available.includes(option); });
    if (!available.some(option => option.value === documentType.value)) {
      documentType.value = available[0]?.value || '';
      documentType.dispatchEvent(new Event('change', { bubbles: true }));
    }
  };
  submissionKind.addEventListener('change', filterDocumentStages);
  filterDocumentStages();
}

const submissionSearch = document.querySelector('[data-submission-search]');
const submissionStage = document.querySelector('[data-submission-stage]');
const submissionStatus = document.querySelector('[data-submission-status]');
const submissionRows = [...document.querySelectorAll('[data-submission-row]')];
if (submissionRows.length) {
  let page = 1;
  const pageSize = 6;
  const tbody = submissionRows[0].parentElement;
  const emptyRow = document.createElement('tr');
  emptyRow.hidden = true;
  emptyRow.innerHTML = '<td colspan="6" class="text-muted">No submissions match these filters.</td>';
  tbody.append(emptyRow);
  const pagination = document.createElement('div');
  pagination.className = 'submission-pagination';
  pagination.innerHTML = '<span data-page-summary></span><div><button class="btn btn-sm btn-outline-secondary" type="button" data-page-prev>Previous</button><button class="btn btn-sm btn-primary" type="button" data-page-number>1</button><button class="btn btn-sm btn-outline-secondary" type="button" data-page-next>Next</button></div>';
  tbody.closest('.table-responsive')?.parentElement.append(pagination);
  const summary = pagination.querySelector('[data-page-summary]');
  const prev = pagination.querySelector('[data-page-prev]');
  const next = pagination.querySelector('[data-page-next]');
  const number = pagination.querySelector('[data-page-number]');
  const filterSubmissions = () => {
    const query = (submissionSearch?.value || '').trim().toLocaleLowerCase();
    const stage = submissionStage?.value || '';
    const status = submissionStatus?.value || '';
    const filtered = submissionRows.filter(row => (!query || row.textContent.toLocaleLowerCase().includes(query))
        && (!stage || row.dataset.stage === stage)
        && (!status || row.dataset.status === status));
    const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
    page = Math.min(page, pageCount);
    const visible = new Set(filtered.slice((page - 1) * pageSize, page * pageSize));
    submissionRows.forEach(row => { row.hidden = !visible.has(row); });
    emptyRow.hidden = filtered.length > 0;
    const from = filtered.length ? (page - 1) * pageSize + 1 : 0;
    const to = Math.min(page * pageSize, filtered.length);
    summary.textContent = `Showing ${from}–${to} of ${filtered.length} entries`;
    number.textContent = String(page);
    prev.disabled = page <= 1;
    next.disabled = page >= pageCount;
  };
  [submissionSearch, submissionStage, submissionStatus].forEach(control => {
    control?.addEventListener('input', filterSubmissions);
    control?.addEventListener('change', filterSubmissions);
  });
  prev.addEventListener('click', () => { page = Math.max(1, page - 1); filterSubmissions(); });
  next.addEventListener('click', () => { page += 1; filterSubmissions(); });
  filterSubmissions();
}

document.querySelectorAll('form[data-submitting-message]').forEach(form => {
  form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    form.setAttribute('aria-busy', 'true');
    const status = document.createElement('p');
    status.className = 'submit-feedback';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    status.textContent = form.dataset.submittingMessage || 'Submitting. Please wait.';
    form.append(status);
    const button = event.submitter;
    if (button && !button.name) button.disabled = true;
  });
});

/* Design-layer enhancements (presentation only) */
document.querySelectorAll('input[type="password"]').forEach(input => {
  if (input.closest('.pw-field')) return;
  const wrap = document.createElement('div');
  wrap.className = 'pw-field';
  input.parentNode.insertBefore(wrap, input);
  wrap.append(input);
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'pw-toggle';
    toggle.setAttribute('aria-label', 'Show password');
  toggle.addEventListener('click', () => {
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
  });
  wrap.append(toggle);
});

const topbar = document.querySelector('.app-topbar');
if (topbar) {
  const syncTopbar = () => topbar.classList.toggle('is-scrolled', window.scrollY > 4);
  window.addEventListener('scroll', syncTopbar, { passive: true });
  syncTopbar();
}

document.querySelectorAll('.stat-value').forEach(el => {
  if (!/^[\d.,%\s]+$/.test(el.textContent.trim())) el.classList.add('is-text');
});


/* Client-side list filtering, auto-submit selects and password confirmation hints */
(() => {
  const searches = document.querySelectorAll('[data-live-filter]');
  const columnFilters = document.querySelectorAll('[data-column-filter]');
  const rowText = row => (row.dataset.filterText || row.textContent).toLowerCase();
  const apply = () => {
    const search = searches[0];
    const term = search ? search.value.trim().toLowerCase() : '';
    const rows = search ? document.querySelectorAll(search.dataset.liveFilter) : [];
    let shown = 0;
    rows.forEach(row => {
      let visible = !term || rowText(row).includes(term);
      columnFilters.forEach(select => {
        if (!visible || !select.value) return;
        const cell = row.children[Number(select.dataset.columnFilter)];
        if (!cell || cell.textContent.trim().toLowerCase() !== select.value.toLowerCase()) visible = false;
      });
      row.hidden = !visible;
      if (visible) shown++;
    });
    const counter = document.getElementById('historyCount');
    if (counter) counter.textContent = `Showing ${shown} of ${rows.length} entries`;
  };
  searches.forEach(input => input.addEventListener('input', apply));
  columnFilters.forEach(select => select.addEventListener('change', apply));
  document.querySelectorAll('select[data-autosubmit]').forEach(select => select.addEventListener('change', () => select.form && select.form.submit()));
  document.querySelectorAll('input[data-match]').forEach(input => {
    const other = document.querySelector(input.dataset.match);
    const note = document.getElementById('matchNote');
    const check = () => {
      const mismatch = input.value.length > 0 && other && input.value !== other.value;
      input.classList.toggle('is-invalid', Boolean(mismatch));
      if (note) note.hidden = !mismatch;
    };
    input.addEventListener('input', check);
    if (other) other.addEventListener('input', check);
  });
})();
