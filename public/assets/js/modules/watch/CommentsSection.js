import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js';
import { ToastManager } from '../../core/components/toast-manager.js';

export class CommentsSection {
    constructor(videoUuid, currentUserAvatar) {
        this.videoUuid = videoUuid;
        this.currentUserAvatar = currentUserAvatar;
        
        this.state = {
            isLoading: false,
            offset: 0,
            limit: 20,
            total: 0
        };
        
        this.listContainer = document.getElementById('comments-list');
        
        this.init();
    }

    init() {
        this._initMainInput();
        this.loadComments();
        this._attachReplyDelegation();
    }

    _initMainInput() {
        const mainInput = document.getElementById('comment-input-main');
        const mainBtn = document.getElementById('btn-submit-main');
        const cancelBtn = document.getElementById('btn-cancel-main');
        const actionsDiv = document.getElementById('comment-actions-main');

        if (mainInput && mainBtn && actionsDiv) {
            mainInput.addEventListener('focus', () => {
                actionsDiv.classList.remove('hidden');
            });

            mainInput.addEventListener('input', () => {
                mainBtn.disabled = mainInput.value.trim().length === 0;
                mainInput.style.height = 'auto';
                mainInput.style.height = mainInput.scrollHeight + 'px';
            });

            cancelBtn.addEventListener('click', () => {
                mainInput.value = '';
                mainInput.style.height = 'auto';
                actionsDiv.classList.add('hidden');
            });

            mainBtn.addEventListener('click', () => {
                this.postComment(mainInput.value, null, () => {
                    mainInput.value = '';
                    mainInput.style.height = 'auto';
                    actionsDiv.classList.add('hidden');
                });
            });
        }
    }

    _attachReplyDelegation() {
        if (this.listContainer) {
            this.listContainer.addEventListener('click', (e) => {
                const replyBtn = e.target.closest('.js-reply-trigger');
                if (replyBtn) {
                    const commentId = replyBtn.dataset.id;
                    this.toggleReplyBox(commentId);
                }
            });
        }
    }

    async loadComments() {
        if (!this.listContainer) return;
        this.state.isLoading = true;

        try {
            const response = await ApiService.post(ApiRoutes.Interaction.LoadComments, {
                video_uuid: this.videoUuid,
                limit: this.state.limit,
                offset: this.state.offset
            });

            if (response.success) {
                this.listContainer.innerHTML = '';
                this.state.total = response.total_count;

                if (response.comments.length === 0) {
                    this.listContainer.innerHTML = `
                        <div style="text-align:center; color:var(--text-secondary); padding: 20px;">
                            <p>Sé el primero en comentar.</p>
                        </div>`;
                    return;
                }

                response.comments.forEach(comment => {
                    const html = this.renderCommentItem(comment);
                    this.listContainer.insertAdjacentHTML('beforeend', html);
                });

            } else {
                this.listContainer.innerHTML = '<p style="color:red; text-align:center;">Error cargando comentarios.</p>';
            }
        } catch (e) {
            console.error('Error comments:', e);
        } finally {
            this.state.isLoading = false;
        }
    }

    renderCommentItem(comment, isReply = false) {
        const isLogged = this.currentUserAvatar !== null;
        
        let repliesHtml = '';
        if (comment.replies && comment.replies.length > 0) {
            repliesHtml = `<div class="component-comment-replies">`;
            comment.replies.forEach(reply => {
                repliesHtml += this.renderCommentItem(reply, true);
            });
            repliesHtml += `</div>`;
        }

        const dateStr = new Date(comment.created_at).toLocaleDateString();

        return `
        <div class="component-comment-item ${isReply ? 'is-reply' : ''}" id="comment-${comment.id}">
            <a href="/ProjectAurora/channel/${comment.user_uuid}" class="component-comment-avatar-link">
                <img src="${comment.avatar_url}" alt="${comment.username}" class="component-comment-avatar">
            </a>
            <div class="component-comment-content">
                <div class="component-comment-header">
                    <span class="component-comment-author">${comment.username}</span>
                    <span class="component-comment-date">${dateStr}</span>
                </div>
                <div class="component-comment-text">${comment.content}</div>
                
                <div class="component-comment-actions">
                    <button class="component-button icon-only small" title="Me gusta">
                        <span class="material-symbols-rounded" style="font-size: 18px;">thumb_up</span>
                    </button>
                    <button class="component-button icon-only small" title="No me gusta">
                        <span class="material-symbols-rounded" style="font-size: 18px;">thumb_down</span>
                    </button>
                    
                    ${isLogged ? `
                        <button class="component-button text small js-reply-trigger" data-id="${comment.id}">
                            Responder
                        </button>
                    ` : ''}
                </div>

                <div id="reply-box-container-${comment.id}" class="component-reply-box-container"></div>

                ${repliesHtml}
            </div>
        </div>
        `;
    }

    toggleReplyBox(commentId) {
        const container = document.getElementById(`reply-box-container-${commentId}`);
        if (!container) return;

        if (container.innerHTML !== '') {
            container.innerHTML = '';
            return;
        }

        document.querySelectorAll('.component-reply-box-container').forEach(el => el.innerHTML = '');

        const myAvatar = this.currentUserAvatar;
        
        container.innerHTML = `
            <div class="component-watch-comment-input-row reply-mode">
                <img src="${myAvatar}" class="component-watch-avatar small">
                <div class="component-watch-comment-input-wrapper">
                    <div class="component-input-group">
                        <textarea id="reply-input-${commentId}" class="component-input auto-expand" placeholder="Añade una respuesta..." rows="1"></textarea>
                    </div>
                    <div class="component-watch-comment-actions" style="display:flex;">
                        <button class="component-button text small js-cancel-reply">Cancelar</button>
                        <button class="component-button primary small js-submit-reply" disabled>Responder</button>
                    </div>
                </div>
            </div>
        `;

        const input = document.getElementById(`reply-input-${commentId}`);
        const btnSubmit = container.querySelector('.js-submit-reply');
        const btnCancel = container.querySelector('.js-cancel-reply');

        input.focus();

        input.addEventListener('input', () => {
            btnSubmit.disabled = input.value.trim().length === 0;
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';
        });

        btnCancel.addEventListener('click', () => {
            container.innerHTML = '';
        });

        btnSubmit.addEventListener('click', () => {
            this.postComment(input.value, commentId, () => {
                container.innerHTML = '';
            });
        });
    }

    async postComment(content, parentId = null, onSuccess) {
        if (this.state.isLoading) return;
        
        try {
            const response = await ApiService.post(ApiRoutes.Interaction.PostComment, {
                video_uuid: this.videoUuid,
                content: content,
                parent_id: parentId
            });

            if (response.success) {
                ToastManager.show('Comentario publicado', 'success');
                if (onSuccess) onSuccess();
                this.injectNewComment(response.comment);
            } else {
                ToastManager.show(response.message || 'Error al comentar', 'error');
            }

        } catch (e) {
            console.error("Post comment error:", e);
            ToastManager.show('Error de conexión', 'error');
        }
    }

    injectNewComment(commentData) {
        if (!commentData.parent_id) {
            const html = this.renderCommentItem(commentData);
            this.listContainer.insertAdjacentHTML('afterbegin', html);
            
            if (this.state.total === 0) {
                const emptyMsg = this.listContainer.querySelector('div[style*="text-align:center"]');
                if (emptyMsg) emptyMsg.remove();
            }
            this.state.total++;
        } 
        else {
            const parentId = commentData.parent_id;
            const parentNode = document.getElementById(`comment-${parentId}`);
            
            if (parentNode) {
                const contentDiv = parentNode.querySelector('.component-comment-content');
                let repliesContainer = contentDiv.querySelector('.component-comment-replies');
                
                if (!repliesContainer) {
                    repliesContainer = document.createElement('div');
                    repliesContainer.className = 'component-comment-replies';
                    contentDiv.appendChild(repliesContainer);
                }

                const html = this.renderCommentItem(commentData, true);
                repliesContainer.insertAdjacentHTML('beforeend', html);
            }
        }
    }
}