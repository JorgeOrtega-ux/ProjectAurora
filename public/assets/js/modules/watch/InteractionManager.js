import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';

export class InteractionManager {
    constructor(videoElement, videoUuid, channelUuid) {
        this.video = videoElement;
        this.videoUuid = videoUuid;
        this.channelUuid = channelUuid;
        
        this.state = {
            viewRegistered: false,
            isLoading: false
        };

        this._initControls();
        this._initViewTracker();
    }

    _initControls() {
        const btnLike = document.querySelector('.js-btn-like');
        const btnDislike = document.querySelector('.js-btn-dislike');
        const btnSubscribe = document.querySelector('.js-btn-subscribe');
        const btnShare = document.querySelector('.js-btn-share');

        if (btnLike) btnLike.addEventListener('click', () => this.handleInteraction('like'));
        if (btnDislike) btnDislike.addEventListener('click', () => this.handleInteraction('dislike'));
        if (btnSubscribe) btnSubscribe.addEventListener('click', () => this.handleSubscribe());
        if (btnShare) btnShare.addEventListener('click', () => this.handleShare());
    }

    _initViewTracker() {
        if (!this.video) return;

        this.video.addEventListener('timeupdate', () => {
            if (this.state.viewRegistered) return;

            if (!this.video.paused && !this.video.seeking) {
                // Contar visita después de 5 segundos de reproducción real
                if (this.video.currentTime > 5) {
                    this._registerView();
                }
            }
        });
    }

    async _registerView() {
        if (this.state.viewRegistered) return;
        this.state.viewRegistered = true;

        try {
            await ApiService.post(ApiRoutes.Interaction.RegisterView, {
                video_uuid: this.videoUuid
            });
        } catch (e) {
            console.warn('View registration failed', e);
        }
    }

    async handleShare() {
        const shareUrl = window.location.origin + '/ProjectAurora/watch?v=' + this.videoUuid;

        DialogManager.confirm({
            title: 'Compartir Video',
            type: 'share', 
            url: shareUrl, 
            confirmText: 'Cerrar', 
            cancelText: null, 
            onReady: (modal) => {
                const btnCopy = modal.querySelector('#btn-copy-link');
                const input = modal.querySelector('#share-url-input');

                if (btnCopy && input) {
                    btnCopy.onclick = () => {
                        input.select();
                        input.setSelectionRange(0, 99999); 

                        navigator.clipboard.writeText(shareUrl).then(() => {
                            ToastManager.show('Enlace copiado al portapapeles', 'success');
                            
                            const originalHtml = btnCopy.innerHTML;
                            btnCopy.innerHTML = '<span class="material-symbols-rounded" style="font-size: 18px; margin-right: 4px;">check</span> Copiado';
                            btnCopy.classList.add('success');
                            
                            setTimeout(() => {
                                btnCopy.innerHTML = originalHtml;
                                btnCopy.classList.remove('success');
                            }, 2000);
                        }).catch(err => {
                            console.error('Error al copiar: ', err);
                            ToastManager.show('No se pudo copiar el enlace', 'error');
                        });
                    };
                }
            }
        });

        try {
            ApiService.post(ApiRoutes.Interaction.Share, {
                video_uuid: this.videoUuid
            });
        } catch (e) {
            console.warn('Error registrando share:', e);
        }
    }

    async handleInteraction(type) {
        if (this.state.isLoading) return;
        this.state.isLoading = true;

        try {
            const response = await ApiService.post(ApiRoutes.Interaction.ToggleLike, {
                video_uuid: this.videoUuid,
                type: type
            });

            if (response.success) {
                this._updateInteractionUI(response);
            } else if (response.require_login) {
                ToastManager.show(I18nManager.t('auth.login_required') || 'Inicia sesión para interactuar', 'info');
            } else {
                ToastManager.show(response.message || 'Error', 'error');
            }
        } catch (error) {
            console.error('Interaction error:', error);
        } finally {
            this.state.isLoading = false;
        }
    }

    _updateInteractionUI(data) {
        const { action, likes, dislikes, type } = data;
        
        const btnLike = document.querySelector('.js-btn-like');
        const btnDislike = document.querySelector('.js-btn-dislike');
        const countLike = document.querySelector('.js-count-like');
        const countDislike = document.querySelector('.js-count-dislike');

        if (btnLike) btnLike.classList.remove('active');
        if (btnDislike) btnDislike.classList.remove('active');

        if (action !== 'removed') {
            if (type === 'like' && btnLike) btnLike.classList.add('active');
            if (type === 'dislike' && btnDislike) btnDislike.classList.add('active');
        }

        if (countLike) countLike.textContent = this._formatNumber(likes);
        if (countDislike) countDislike.textContent = this._formatNumber(dislikes);
    }

    async handleSubscribe() {
        if (this.state.isLoading) return;
        if (!this.channelUuid) return;

        this.state.isLoading = true;
        const btn = document.querySelector('.js-btn-subscribe');
        if (!btn) return;
        
        const originalText = btn.innerHTML;
        btn.classList.add('loading'); 

        try {
            const response = await ApiService.post(ApiRoutes.Interaction.ToggleSub, {
                channel_uuid: this.channelUuid
            });

            if (response.success) {
                const isSubscribed = response.subscribed;
                const countSubs = document.querySelector('.js-count-subs');

                if (isSubscribed) {
                    btn.classList.add('subscribed');
                    btn.textContent = I18nManager.t('app.subscribed') || 'Suscrito';
                    ToastManager.show(I18nManager.t('app.sub_success') || 'Suscripción añadida', 'success');
                } else {
                    btn.classList.remove('subscribed');
                    btn.textContent = I18nManager.t('app.subscribe') || 'Suscribirse';
                    ToastManager.show(I18nManager.t('app.unsub_success') || 'Suscripción eliminada', 'success');
                }

                if (countSubs) {
                    countSubs.textContent = this._formatNumber(response.subscribers_count);
                }
            } else if (response.require_login) {
                ToastManager.show('Inicia sesión para suscribirte', 'info');
            } else {
                ToastManager.show(response.message, 'error');
            }
        } catch (error) {
            console.error('Subscribe error:', error);
            btn.innerHTML = originalText;
        } finally {
            this.state.isLoading = false;
            btn.classList.remove('loading');
        }
    }

    _formatNumber(num) {
        if (num === null || num === undefined) return '0';
        num = parseInt(num);
        if (isNaN(num)) return '0';

        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        }
        return num.toString();
    }
}