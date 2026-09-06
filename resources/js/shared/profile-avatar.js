import { setupProfileAvatarPicker } from '../profile-avatar-picker.js';

document.addEventListener('DOMContentLoaded', () => {
  setupProfileAvatarPicker({
    triggerSelectors: ['#changePhoto', '#changePhotoBtn', '#avatarPreview'],
  });
});
