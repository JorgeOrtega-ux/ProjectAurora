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
        this._attachDelegation();
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

    _attachDelegation() {
        if (this.listContainer) {
            this.listContainer.addEventListener('click', (e) => {
                // 1. Delegación para Responder
                const replyBtn = e.target.closest('.js-reply-trigger');
                if (replyBtn) {
                    const commentId = replyBtn.dataset.id;
                    this.toggleReplyBox(commentId);
                    return;
                }

                // 2. Delegación para Like
                const likeBtn = e.target.closest('.js-like-trigger');
                if (likeBtn) {
                    const commentId = likeBtn.dataset.id;
                    this.toggleInteraction(commentId, 'like');
                    return;
                }

                // 3. Delegación para Dislike
                const dislikeBtn = e.target.closest('.js-dislike-trigger');
                if (dislikeBtn) {
                    const commentId = dislikeBtn.dataset.id;
                    this.toggleInteraction(commentId, 'dislike');
                    return;
                }
            });
        }
    }

    async loadComments() {
        if (!this.listContainer) return;
        this.state.isLoading = true;

        try {
            // Nota: Se asume que LoadComments ahora devuelve stats (likes_count, user_interaction)
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
    
    // 1. Generar HTML de respuestas (Recursividad)
    let repliesHtml = '';
    if (comment.replies && comment.replies.length > 0) {
        repliesHtml = `<div class="component-comment-replies">`;
        comment.replies.forEach(reply => {
            // Nota: Pasamos true para indicar que es una respuesta
            repliesHtml += this.renderCommentItem(reply, true);
        });
        repliesHtml += `</div>`;
    }

    const dateStr = new Date(comment.created_at).toLocaleDateString();

    // 2. Determinar estado activo de los botones (Visual)
    const activeLike = comment.user_interaction === 'like' ? 'active' : '';
    const activeDislike = comment.user_interaction === 'dislike' ? 'active' : '';

    // 3. Formatear contadores (para no mostrar "0")
    const likesCount = comment.likes_count > 0 ? comment.likes_count : '';
    const dislikesCount = comment.dislikes_count > 0 ? comment.dislikes_count : '';

    return `
    <div class="component-comment-item ${isReply ? 'is-reply' : ''}" id="comment-${comment.id}">
        <a href="/ProjectAurora/channel/${comment.user_uuid}" class="component-comment-avatar-link">
            <img src="${comment.avatar_url}" alt="${comment.username}" class="component-comment-avatar">
        </a>
        
        <div class="component-comment-content">
            <div class="component-comment-header">
                <a href="/ProjectAurora/channel/${comment.user_uuid}" class="component-comment-author">${comment.username}</a>
                <span class="component-comment-date">${dateStr}</span>
            </div>
            
            <div class="component-comment-text">${comment.content}</div>
            
            <div class="component-comment-actions">
                <button class="component-button icon-only small js-like-trigger ${activeLike}" data-id="${comment.id}" title="Me gusta">
                    <span class="material-symbols-rounded" style="font-size: 18px;">thumb_up</span>
                    <span class="count-label" style="font-size: 12px; margin-left: 4px;">${likesCount}</span>
                </button>

                <button class="component-button icon-only small js-dislike-trigger ${activeDislike}" data-id="${comment.id}" title="No me gusta">
                    <span class="material-symbols-rounded" style="font-size: 18px;">thumb_down</span>
                    <span class="count-label" style="font-size: 12px; margin-left: 4px;">${dislikesCount}</span>
                </button>
                
                ${isLogged && !isReply ? `
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

        // Cerrar otras cajas abiertas para limpieza visual
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

    // --- MODIFICACIÓN 3: Lógica Interactiva (Like/Dislike) ---
   // Archivo: public/assets/js/modules/watch/CommentsSection.js

async toggleInteraction(commentId, type) {
    if (!this.currentUserAvatar) {
        ToastManager.show('Debes iniciar sesión', 'info');
        return;
    }

    const commentEl = document.getElementById(`comment-${commentId}`);
    if (!commentEl) return;

    // Selectores actualizados para buscar dentro del elemento
    const btnLike = commentEl.querySelector('.js-like-trigger');
    const btnDislike = commentEl.querySelector('.js-dislike-trigger');
    
    // Referencia al contador correcto según el tipo
    const targetBtn = type === 'like' ? btnLike : btnDislike;
    const countLabel = targetBtn.querySelector('.count-label');
try {
        // CORRECCIÓN: Usamos el OBJETO de ApiRoutes, no el string.
        const response = await ApiService.post(ApiRoutes.Interaction.CommentLike, {
            comment_id: commentId,
            type: type
        });

        if (response.success) {
            // Limpiar clases activas
            btnLike.classList.remove('active');
            btnDislike.classList.remove('active');

            // Actualizar contadores visuales (usando los datos frescos del servidor)
            const labelLike = btnLike.querySelector('.count-label');
            const labelDislike = btnDislike.querySelector('.count-label');

            labelLike.textContent = response.likes > 0 ? response.likes : '';
            labelDislike.textContent = response.dislikes > 0 ? response.dislikes : '';

            // Activar botón si corresponde
            if (response.action !== 'removed') {
                if (response.type === 'like') btnLike.classList.add('active');
                if (response.type === 'dislike') btnDislike.classList.add('active');
            }
        } else {
            ToastManager.show(response.message || 'Error al interactuar', 'error');
        }
    } catch (e) {
        console.error(e);
    }
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
            // Lógica para inyectar respuesta
            // Buscamos el padre visual correcto
            // Si el padre original era una respuesta, el sistema de backend lo aplanó al abuelo.
            // Buscamos el elemento DOM del parent_id retornado por el backend.
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

                // Renderizamos como respuesta (isReply = true)
                const html = this.renderCommentItem(commentData, true);
                repliesContainer.insertAdjacentHTML('beforeend', html);
            }
        }
    }
}