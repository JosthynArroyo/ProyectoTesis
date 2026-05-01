import { setupProfileAvatarPicker } from '../profile-avatar-picker.js';

document.addEventListener('DOMContentLoaded', () => {
  setupAvatarPicker();
});

function setupAvatarPicker() {
  setupProfileAvatarPicker({
    triggerSelectors: ['#changePhoto', '#avatarPreview'],
  });
}
