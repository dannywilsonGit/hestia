import { open, confirm } from "@tauri-apps/plugin-dialog";
import { ENGINE_BASE_URL, ENGINE_API_PREFIX } from "./config";
import { openPath, openUrl } from '@tauri-apps/plugin-opener';

// DOM Elements
const chooseBtn = document.getElementById("choose-folder");
const folderPathEl = document.getElementById("folder-path");
const templateSelect = document.getElementById("template-select");
const nextToScanBtn = document.getElementById("next-to-scan");
const nextToPlanBtn = document.getElementById("next-to-plan");
const nextToExecuteBtn = document.getElementById("next-to-execute");
const prevFromScanBtn = document.getElementById("prev-from-scan");
const prevFromPlanBtn = document.getElementById("prev-from-plan");
const prevFromExecuteBtn = document.getElementById("prev-from-execute");
const undoApplyBtn = document.getElementById("undo-apply");
const openExplorerBtn = document.getElementById("open-explorer");
const closeSuccessBtn = document.getElementById("close-success");

// State variables
let selectedPath = null;
let scanId = null;
let planId = null;
let applyId = null;
let scanPollTimer = null;
let applyPollTimer = null;

// Page navigation
let currentPage = 1;
const pages = document.querySelectorAll('.page');
const progressSteps = document.querySelectorAll('.progress-step');


// Gestion des états du folder
const folderCard = document.getElementById('drop-zone');
const defaultState = document.querySelector('.folder-state-default');
const selectedState = document.querySelector('.folder-state-selected');
const folderPathSpan = document.getElementById('folder-path');


// Modifie le click du bouton choose-folder existant
chooseBtn.addEventListener("click", async () => {
  const result = await open({ directory: true, multiple: false });
  if (typeof result === "string") {
    selectedPath = result;
    
    // Afficher l'état sélectionné
    defaultState.style.display = 'none';
    selectedState.style.display = 'block';
    folderPathSpan.textContent = result;
    folderPathSpan.style.color = '#00ff88';
    
    
    nextToScanBtn.disabled = false;
    
    // Animation de succès
    showToast('Folder selected successfully!', 'success');
  }
});

// Bouton pour changer de dossier
const changeFolderBtn = document.getElementById('change-folder');
if (changeFolderBtn) {
  changeFolderBtn.addEventListener('click', async () => {
    const result = await open({ directory: true, multiple: false });
    if (typeof result === "string") {
      selectedPath = result;
      folderPathSpan.textContent = result;
      showToast('Folder changed successfully!', 'success');
    }
  });
}

// Drag & Drop support
folderCard.addEventListener('dragover', (e) => {
  e.preventDefault();
  folderCard.classList.add('drag-over');
});

folderCard.addEventListener('dragleave', () => {
  folderCard.classList.remove('drag-over');
});

folderCard.addEventListener('drop', async (e) => {
  e.preventDefault();
  folderCard.classList.remove('drag-over');
  
  const files = Array.from(e.dataTransfer.files);
  const folder = files.find(f => f.type === '' && f.size === 0); // Détection simple de dossier
  
  if (folder && folder.path) {
    selectedPath = folder.path;
    defaultState.style.display = 'none';
    selectedState.style.display = 'block';
    folderPathSpan.textContent = selectedPath;
    nextToScanBtn.disabled = false;
    quickStats.style.display = 'flex';
    showToast('Folder dropped successfully!', 'success');
  } else {
    showToast('Please drop a folder, not files', 'error');
  }
});

// ============================================
// SIDEBAR FUNCTIONALITY - À AJOUTER
// ============================================

// Sidebar elements
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebarClose = document.getElementById('sidebar-close');
const sidebarItems = document.querySelectorAll('.sidebar-item');

// Sidebar state (persist in localStorage) - DÉPLIÉ PAR DÉFAUT
let isSidebarOpen = localStorage.getItem('sidebarOpen') !== 'false';

// Initialize sidebar state
function initSidebar() {
  if (isSidebarOpen) {
    sidebar.classList.remove('collapsed');
  } else {
    sidebar.classList.add('collapsed');
  }
  
  // Update toggle icon rotation
  const toggleIcon = document.querySelector('.toggle-icon');
  if (toggleIcon) {
    toggleIcon.style.transform = isSidebarOpen ? 'rotate(0deg)' : 'rotate(180deg)';
  }
}

// Toggle sidebar
function toggleSidebar() {
  isSidebarOpen = !isSidebarOpen;
  localStorage.setItem('sidebarOpen', isSidebarOpen);
  
  if (isSidebarOpen) {
    sidebar.classList.remove('collapsed');
  } else {
    sidebar.classList.add('collapsed');
  }
  
  // Update toggle icon
  const toggleIcon = document.querySelector('.toggle-icon');
  if (toggleIcon) {
    toggleIcon.style.transform = isSidebarOpen ? 'rotate(0deg)' : 'rotate(180deg)';
  }
  
  // Add overlay for mobile
  if (window.innerWidth <= 768 && isSidebarOpen) {
    createOverlay();
  } else {
    removeOverlay();
  }
}

// Create overlay for mobile
function createOverlay() {
  let overlay = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', () => {
      toggleSidebar();
      removeOverlay();
    });
  }
  setTimeout(() => overlay.classList.add('active'), 10);
}

// Remove overlay
function removeOverlay() {
  const overlay = document.querySelector('.sidebar-overlay');
  if (overlay) {
    overlay.classList.remove('active');
    setTimeout(() => overlay.remove(), 300);
  }
}

// Handle sidebar item clicks
function initSidebarItems() {
  sidebarItems.forEach(item => {
    item.addEventListener('click', (e) => {
      const action = item.dataset.action;
      if (!action) return;
      
      // Visual feedback
      item.style.transform = 'scale(0.95)';
      setTimeout(() => {
        item.style.transform = '';
      }, 200);
      
      // Handle different actions
      handleSidebarAction(action);
      
      // On mobile, close sidebar after action
      if (window.innerWidth <= 768) {
        toggleSidebar();
      }
    });
  });
}

// Handle sidebar actions (à compléter plus tard)
function handleSidebarAction(action) {
  switch(action) {
    case 'export':
      showToast('Export feature coming soon!', 'info');
      break;
    case 'import':
      showToast('Import feature coming soon!', 'info');
      break;
    case 'backup':
      showToast('Backup feature coming soon!', 'info');
      break;
    case 'preferences':
      showToast('Preferences coming soon!', 'info');
      break;
    case 'templates':
      showToast('Template manager coming soon!', 'info');
      break;
    case 'rules':
      showToast('Custom rules coming soon!', 'info');
      break;
    case 'recent':
      showToast('Recent organizes coming soon!', 'info');
      break;
    case 'logs':
      showToast('Activity logs coming soon!', 'info');
      break;
    case 'help':
      showToast('Help & documentation coming soon!', 'info');
      break;
    case 'about':
      showAboutModal();
      break;
    default:
      console.log('Action:', action);
  }
}

// Simple about modal
function showAboutModal() {
  const aboutContent = `
    <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                background: rgba(10,15,30,0.95); backdrop-filter: blur(20px); 
                border: 1px solid var(--neon-cyan); border-radius: 20px; 
                padding: 30px; z-index: 10000; max-width: 400px; text-align: center;">
      <div style="font-size: 48px; margin-bottom: 20px;">🏠</div>
      <h2 style="margin-bottom: 10px;">HESTIA</h2>
      <p style="color: var(--text-secondary); margin-bottom: 20px;">Version 1.0.0</p>
      <p style="color: var(--text-dim); font-size: 14px; margin-bottom: 20px;">
        Intelligent file organizer for your digital space.
      </p>
      <button id="close-about" style="background: var(--neon-cyan); border: none; 
              padding: 10px 24px; border-radius: 10px; cursor: pointer; color: white; font-weight: 600;">
        Close
      </button>
    </div>
  `;
  
  const modal = document.createElement('div');
  modal.innerHTML = aboutContent;
  document.body.appendChild(modal);
  
  document.getElementById('close-about').addEventListener('click', () => {
    modal.remove();
  });
}

// Update sidebar stats (à appeler après un scan réussi)
function updateSidebarStats(filesCount, spaceSaved) {
  const statsFiles = document.getElementById('sidebar-stats-files');
  const statsSpace = document.getElementById('sidebar-stats-space');
  
  if (statsFiles) {
    const current = parseInt(statsFiles.textContent) || 0;
    statsFiles.textContent = current + filesCount;
  }
  
  if (statsSpace) {
    statsSpace.textContent = spaceSaved || '0 MB';
  }
}

// Initialize sidebar when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initSidebarItems();
  
  // Toggle button event
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
  }
  
  // Close button event
  if (sidebarClose) {
    sidebarClose.addEventListener('click', toggleSidebar);
  }
  
  // Close sidebar on ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isSidebarOpen && window.innerWidth <= 768) {
      toggleSidebar();
    }
  });
  
  // Handle window resize
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      removeOverlay();
    }
  });
});

// Toast notification function (si pas déjà présente)
function showToast(message, type = 'info') {
  // Remove existing toast
  const existingToast = document.querySelector('.toast');
  if (existingToast) existingToast.remove();
  
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <div class="toast-content">
      <span class="toast-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
      <span>${message}</span>
    </div>
  `;
  document.body.appendChild(toast);
  
  setTimeout(() => toast.classList.add('show'), 100);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// CSS pour les toasts (ajouter dans le CSS si pas déjà présent)
const toastStyle = document.createElement('style');
toastStyle.textContent = `
  .toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid var(--neon-cyan);
    border-radius: 12px;
    padding: 12px 20px;
    transform: translateX(400px);
    transition: transform 0.3s;
    z-index: 10001;
  }
  .toast.show {
    transform: translateX(0);
  }
  .toast-success { border-color: var(--neon-green); }
  .toast-error { border-color: var(--neon-pink); }
  .toast-content {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .toast-icon {
    font-size: 20px;
    font-weight: 800;
  }
`;
if (!document.querySelector('style[data-toast]')) {
  toastStyle.setAttribute('data-toast', 'true');
  document.head.appendChild(toastStyle);
}

function navigateToPage(pageNumber) {
  // Update pages
  pages.forEach((page, index) => {
    const pageNum = index + 1;
    if (pageNum === pageNumber) {
      page.classList.add('active');
      page.classList.remove('prev');
    } else if (pageNum < pageNumber) {
      page.classList.remove('active');
      page.classList.add('prev');
    } else {
      page.classList.remove('active', 'prev');
    }
  });
  
  // Update progress steps
  progressSteps.forEach((step, index) => {
    const stepNum = index + 1;
    if (stepNum === pageNumber) {
      step.classList.add('active');
    } else {
      step.classList.remove('active');
    }
    
    if (stepNum < pageNumber) {
      step.classList.add('completed');
    } else {
      step.classList.remove('completed');
    }
  });
  
  currentPage = pageNumber;
  
  // Trigger page-specific actions
  if (pageNumber === 2 && scanId) {
    startScanPolling();
  }
  if (pageNumber === 3 && planId) {
    loadPlan();
  }
  if (pageNumber === 4 && applyId) {
    startApplyPolling();
  }
}

// API functions
function apiUrl(path) {
  return `${ENGINE_BASE_URL}${ENGINE_API_PREFIX}${path}`;
}

async function postJson(url, payload) {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const json = await res.json();
  return { res, json };
}

async function getJson(url) {
  const res = await fetch(url);
  const json = await res.json();
  return { res, json };
}

// Update progress circle
function updateScanProgress(percent) {
  const circle = document.querySelector('.progress-ring-fill');
  const percentageEl = document.getElementById('scan-percentage');
  const radius = 52;
  const circumference = 2 * Math.PI * radius;
  
  if (circle) {
    const offset = circumference - (percent / 100) * circumference;
    circle.style.strokeDashoffset = offset;
  }
  if (percentageEl) {
    percentageEl.textContent = `${Math.round(percent)}%`;
  }
}

// Load templates
async function loadTemplates() {
  try {
    const { json } = await getJson(apiUrl("/templates"));
    if (!json.ok) return;
    const ids = json.data.templates || [];
    if (!Array.isArray(ids) || ids.length === 0) return;
    
    templateSelect.innerHTML = "";
    ids.forEach((id) => {
      const opt = document.createElement("option");
      opt.value = id;
      opt.textContent = id;
      templateSelect.appendChild(opt);
    });
    
    if (!ids.includes("downloads_basic")) {
      templateSelect.value = ids[0];
    } else {
      templateSelect.value = "downloads_basic";
    }
  } catch (e) {
    console.warn("Failed to load templates:", e);
  }
}

// Choose folder
/* chooseBtn.addEventListener("click", async () => {
  const result = await open({ directory: true, multiple: false });
  if (typeof result === "string") {
    selectedPath = result;
    folderPathEl.textContent = result;
    folderPathEl.style.color = '#00ff88';
    nextToScanBtn.disabled = false;
  }
}); */

// Next to scan
nextToScanBtn.addEventListener("click", async () => {
  if (!selectedPath) return;
  
  navigateToPage(2);
  
  // Start scan
  const scanStatusEl = document.getElementById("scan-status");
  const scanStateEl = document.getElementById("scan-state");
  const filesFoundEl = document.getElementById("files-found");
  
  scanStatusEl.textContent = "Initializing scan...";
  scanStateEl.textContent = "Scanning";
  updateScanProgress(0);
  
  const { json } = await postJson(apiUrl("/scans"), { path: selectedPath });
  
  if (!json.ok) {
    scanStatusEl.textContent = `Error: ${json.error.message}`;
    return;
  }
  
  scanId = json.data.scanId;
  scanStatusEl.textContent = `Scan in progress...`;
  startScanPolling();
});

function startScanPolling() {
  if (scanPollTimer) clearInterval(scanPollTimer);
  
  scanPollTimer = setInterval(async () => {
    if (!scanId) return;
    
    const { json } = await getJson(apiUrl(`/scans/${scanId}`));
    if (!json.ok) {
      clearInterval(scanPollTimer);
      return;
    }
    
    const d = json.data;
    const percent = d.progress.percent;
    updateScanProgress(percent);
    
    const filesFoundEl = document.getElementById("files-found");
    if (d.summary && d.summary.totalFiles) {
      filesFoundEl.textContent = d.summary.totalFiles;
    }
    
    if (d.status === "done") {
      clearInterval(scanPollTimer);
      document.getElementById("scan-status").textContent = "Scan completed !";
      document.getElementById("scan-state").textContent = "Success";
      nextToPlanBtn.disabled = false;
    }
  }, 900);
}

// Next to plan
nextToPlanBtn.addEventListener("click", async () => {
  if (!scanId) return;
  
  navigateToPage(3);
  
  const planStatusEl = document.getElementById("plan-status");
  const treeContainer = document.getElementById("plan-preview-tree");
  treeContainer.innerHTML = '<div class="loading-state">🔍 Generating organization plan...</div>';
  
  const { json: planResponse } = await postJson(apiUrl("/plans"), {
    scanId,
    template: templateSelect.value,
  });
  
  if (!planResponse.ok) {
    treeContainer.innerHTML = `<div class="error-state">Error: ${planResponse.error.message}</div>`;
    return;
  }
  
  planId = planResponse.data.planId;
  await loadPlan();
});

async function loadPlan() {
  if (!planId) return;
  
  const { json: preview } = await getJson(apiUrl(`/plans/${planId}`));
  if (!preview.ok) return;
  
  const actions = preview.data.actions || [];
  const totalFiles = actions.filter(a => a.type === 'move').length;
  const newFolders = actions.filter(a => a.type === 'mkdir').length;
  
  document.getElementById("stat-files").textContent = totalFiles;
  document.getElementById("stat-folders").textContent = newFolders;
  document.getElementById("plan-stats-summary").style.display = "flex";
  document.getElementById("file-count-badge").textContent = `${actions.length} Actions`;
  
  renderFileTree(actions);
  document.getElementById("apply-warning").style.display = "flex";
  nextToExecuteBtn.disabled = false;
}

function renderFileTree(actions) {
  const treeContainer = document.getElementById("plan-preview-tree");
  treeContainer.innerHTML = "";
  
  if (actions.length === 0) {
    treeContainer.innerHTML = '<div class="empty-state">✨ Your files are already organized!</div>';
    return;
  }
  
  actions.forEach(action => {
    const item = document.createElement("div");
    item.className = `tree-item ${action.type === 'move' ? 'action-move' : 'action-mkdir'}`;
    
    if (action.type === 'move') {
      const fileName = action.from.split(/[\\/]/).pop();
      item.innerHTML = `
        <span class="action-icon">📦</span>
        <span class="tree-path-orig">${fileName}</span>
        <span class="arrow-icon">→</span>
        <span class="tree-path-dest">${action.to}</span>
      `;
    } else {
      item.innerHTML = `
        <span class="action-icon">📁</span>
        <span class="tree-path-dest">Create: ${action.name}</span>
      `;
    }
    
    treeContainer.appendChild(item);
  });
}

// Next to execute
nextToExecuteBtn.addEventListener("click", async () => {
  if (!planId) return;
  
  const ok = await confirm(
    "⚠️ This will physically move files on your disk.\n\nDo you want to continue?",
    { title: "HESTIA", kind: "warning" }
  );
  
  if (!ok) return;
  
  navigateToPage(4);
  
  const applyStatusEl = document.getElementById("apply-status");
  const applyProgressBar = document.getElementById("apply-progress-bar");
  const applyPercentageEl = document.getElementById("apply-percentage");
  
  applyStatusEl.textContent = "Starting organization...";
  applyProgressBar.style.width = "0%";
  applyPercentageEl.textContent = "0%";
  
  const { json } = await postJson(apiUrl("/applies"), { planId });
  
  if (!json.ok) {
    applyStatusEl.textContent = `Error: ${json.error.message}`;
    return;
  }
  
  applyId = json.data.applyId;
  undoApplyBtn.disabled = false;
  startApplyPolling();
});

function startApplyPolling() {
  if (applyPollTimer) clearInterval(applyPollTimer);
  
  applyPollTimer = setInterval(async () => {
    if (!applyId) return;
    
    const { json } = await getJson(apiUrl(`/applies/${applyId}`));
    if (!json.ok) {
      clearInterval(applyPollTimer);
      return;
    }
    
    const d = json.data;
    const percent = d.progress.percent;
    document.getElementById("apply-progress-bar").style.width = `${percent}%`;
    document.getElementById("apply-percentage").textContent = `${Math.round(percent)}%`;
    document.getElementById("apply-status").textContent = d.status;
    
    if (d.status === "done") {
      clearInterval(applyPollTimer);
      showSuccessEffect(d.summary.moved, d.summary.createdFolders);
    }
  }, 900);
}

// Success effect
function showSuccessEffect(moved, createdFolders) {
  const overlay = document.getElementById("success-overlay");
  document.getElementById("final-files").textContent = moved;
  document.getElementById("final-folders").textContent = createdFolders;

  updateSidebarStats(moved, '~' + (moved * 0.5).toFixed(0) + ' MB');
  
  overlay.classList.add("visible");
  triggerConfetti();
}

function triggerConfetti() {
  const canvas = document.getElementById("confetti-canvas");
  const ctx = canvas.getContext("2d");
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
  
  const particles = [];
  const colors = ['#00f3ff', '#bf00ff', '#ff006e', '#00ff88'];
  
  for (let i = 0; i < 150; i++) {
    particles.push({
      x: Math.random() * canvas.width,
      y: Math.random() * canvas.height - canvas.height,
      size: Math.random() * 8 + 4,
      color: colors[Math.floor(Math.random() * colors.length)],
      speedX: (Math.random() - 0.5) * 3,
      speedY: Math.random() * 5 + 2,
    });
  }
  
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles.forEach(p => {
      ctx.fillStyle = p.color;
      ctx.fillRect(p.x, p.y, p.size, p.size);
      p.x += p.speedX;
      p.y += p.speedY;
    });
    
    if (particles[0].y < canvas.height) {
      requestAnimationFrame(draw);
    }
  }
  
  draw();
}

// Navigation back buttons
prevFromScanBtn.addEventListener("click", () => navigateToPage(1));
prevFromPlanBtn.addEventListener("click", () => navigateToPage(2));
prevFromExecuteBtn.addEventListener("click", () => navigateToPage(3));

// Undo
undoApplyBtn.addEventListener("click", async () => {
  if (!applyId) return;
  
  undoApplyBtn.disabled = true;
  document.getElementById("apply-status").textContent = "Undoing changes...";
  
  const { json } = await postJson(apiUrl("/undo"), { applyId });
  
  if (!json.ok) {
    document.getElementById("apply-status").textContent = `Undo error: ${json.error.message}`;
    undoApplyBtn.disabled = false;
    return;
  }
  
  document.getElementById("apply-status").textContent = "Changes undone successfully!";
  setTimeout(() => {
    undoApplyBtn.disabled = false;
  }, 2000);
});

// Open in explorer
if (openExplorerBtn) {
  openExplorerBtn.addEventListener("click", async () => {
    if (selectedPath) {
      await openPath(selectedPath);
    }
  });
}

// Close success modal
closeSuccessBtn.addEventListener("click", () => {
  document.getElementById("success-overlay").classList.remove("visible");
});

// Initialize
loadTemplates();

// Click on progress steps to navigate
progressSteps.forEach((step, index) => {
  step.addEventListener("click", () => {
    const stepNum = index + 1;
    if (stepNum <= currentPage) {
      navigateToPage(stepNum);
    }
  });
});


// ============================================
// SPLASH SCREEN - Animation au démarrage
// ============================================

function initSplashScreen() {
  const splash = document.getElementById('splash-screen');
  if (!splash) return;
  
  // Animation de 3 secondes
  setTimeout(() => {
    splash.classList.add('fade-out');
    
    // Supprimer le splash après l'animation
    setTimeout(() => {
      splash.remove();
    }, 800);
  }, 4000);
}

// Démarrer l'animation du splash au chargement
window.addEventListener('DOMContentLoaded', initSplashScreen);