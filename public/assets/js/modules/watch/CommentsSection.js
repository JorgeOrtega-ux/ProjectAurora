import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js';
import { ToastManager } from '../../core/components/toast-manager.js';

export class CommentsSection {
    constructor(videoUuid, currentUserAvatar) {
        this.videoUuid = videoUuid;
        
        // --- FIX CRÍTICO: Auto-detectar usuario ---
        // Si el controlador no nos pasa el avatar, lo buscamos en el DOM nosotros mismos
        if (!currentUserAvatar) {
            const context = document.querySelector('.js-video-context');
            if (context && context.dataset.userAvatar) {
                this.currentUserAvatar = context.dataset.userAvatar;
            } else {
                this.currentUserAvatar = null;
            }
        } else {
            this.currentUserAvatar = currentUserAvatar;
        }

        // Datos para UI Optimista (Fallback seguro)
        this.currentUserName = window.Aurora?.user?.username || 'Tú';
        this.currentUserUuid = window.Aurora?.user?.uuid || 'me';
        
        this.state = {
            isLoading: false,
            offset: 0,
            limit: 20,
            total: 0
        };

        this.repliesStore = new Map();
        this.listContainer = document.getElementById('comments-list');
        
        this.init();
    }

    init() {
        this._initMainInput();
        this.loadComments();
        this._attachDelegation();
    }

    _attachExpandLogic(input, wrapper) {
        input.addEventListener('focus', () => {
            wrapper.classList.add('is-expanded');
        });

        input.addEventListener('blur', () => {
            setTimeout(() => {
                const hasText = input.value.trim().length > 0;
                if (!hasText) {
                    wrapper.classList.remove('is-expanded');
                    input.style.height = 'auto'; 
                }
            }, 150);
        });

        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';
        });
    }

    _initMainInput() {
        const mainInput = document.getElementById('comment-input-main');
        const mainBtn = document.getElementById('btn-submit-main');
        const MAX_CHARS = 10000;
        
        if (mainInput && mainBtn) {
            const wrapper = document.getElementById('comment-wrapper-box');

            if (wrapper) {
                this._attachExpandLogic(mainInput, wrapper);
            }

            mainInput.addEventListener('input', () => {
                const currentLength = mainInput.value.length;
                const isEmpty = mainInput.value.trim().length === 0;
                
                mainBtn.disabled = isEmpty || currentLength > MAX_CHARS;
            });

            mainBtn.addEventListener('click', () => {
                const text = mainInput.value;
                mainInput.value = '';
                mainInput.style.height = 'auto';
                mainBtn.disabled = true;
                if (wrapper) wrapper.classList.remove('is-expanded');

                this.postComment(text, null, null, () => {
                    mainInput.value = text;
                    if (wrapper) wrapper.classList.add('is-expanded');
                });
            });
            
            mainInput.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.key === 'Enter') {
                    if (mainInput.value.trim().length > 0 && mainInput.value.length <= MAX_CHARS) {
                        mainBtn.click();
                    }
                }
            });
        }
    }

    _attachDelegation() {
        if (this.listContainer) {
            this.listContainer.addEventListener('click', (e) => {
                const replyBtn = e.target.closest('.js-reply-trigger');
                if (replyBtn) {
                    const commentId = replyBtn.dataset.id;
                    this.toggleReplyBox(commentId);
                    return;
                }
                
                const likeBtn = e.target.closest('.js-like-trigger');
                if (likeBtn) {
                    this.toggleInteraction(likeBtn.dataset.id, 'like');
                    return;
                }
                
                const dislikeBtn = e.target.closest('.js-dislike-trigger');
                if (dislikeBtn) {
                    this.toggleInteraction(dislikeBtn.dataset.id, 'dislike');
                    return;
                }

                const toggleRepliesBtn = e.target.closest('.js-toggle-replies');
                if (toggleRepliesBtn) {
                    this.handleRepliesToggle(toggleRepliesBtn.dataset.id, toggleRepliesBtn);
                    return;
                }

                const readMoreBtn = e.target.closest('.js-read-more');
                if (readMoreBtn) {
                    this._handleReadMore(readMoreBtn);
                    return;
                }
            });
        }
    }

    _handleReadMore(btn) {
        const container = btn.closest('.component-comment-text-wrapper');
        const shortText = container.querySelector('.js-text-short');
        const fullText = container.querySelector('.js-text-full');
        const action = btn.dataset.action;

        if (action === 'expand') {
            shortText.style.display = 'none';
            fullText.style.display = 'inline';
            btn.textContent = 'Leer menos';
            btn.dataset.action = 'collapse';
        } else {
            shortText.style.display = 'inline';
            fullText.style.display = 'none';
            btn.textContent = 'Leer más';
            btn.dataset.action = 'expand';
        }
    }

    handleRepliesToggle(commentId, btnElement) {
        const container = document.getElementById(`replies-container-${commentId}`);
        const replies = this.repliesStore.get(commentId) || [];
        const isHiding = btnElement.dataset.action === 'hide';
        
        if (isHiding) {
            container.innerHTML = '';
            btnElement.dataset.action = 'show';
            btnElement.innerHTML = `<span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>Ver ${replies.length} respuestas`;
            return;
        }

        const currentCount = container.children.length;
        const BATCH_SIZE = 10;
        const nextBatch = replies.slice(currentCount, currentCount + BATCH_SIZE);

        nextBatch.forEach(reply => {
            const html = this.renderCommentItem(reply, true);
            container.insertAdjacentHTML('beforeend', html);
        });

        if (currentCount + nextBatch.length >= replies.length) {
            btnElement.dataset.action = 'hide';
            btnElement.innerHTML = `<span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_less</span>Ocultar respuestas`;
        } else {
            btnElement.dataset.action = 'show';
            btnElement.innerHTML = `<span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">subdirectory_arrow_right</span>Mostrar más`;
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
                this.repliesStore.clear(); 

                if (response.comments.length === 0) {
                    this.listContainer.innerHTML = `<div style="text-align:center; color:var(--text-secondary); padding: 20px;"><p>Sé el primero en comentar.</p></div>`;
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
        let repliesUi = '';
        const repliesCount = comment.replies ? comment.replies.length : 0;
        const commentId = comment.id || comment.uuid; 

        const isPending = comment.is_pending === true;
        const opacityStyle = isPending ? 'opacity: 0.6;' : '';
        const pendingBadge = isPending ? '<span class="js-pending-label" style="font-size:10px; color:var(--primary-color); margin-left:5px; font-style:italic;">(Enviando...)</span>' : '';

        if (!isReply) {
             this.repliesStore.set(commentId, comment.replies || []);
             if (repliesCount > 0) {
                repliesUi = `
                    <div class="component-comment-replies-wrapper">
                        <div class="component-comment-replies" id="replies-container-${commentId}"></div>
                        <button class="component-watch-replies-toggle js-toggle-replies" data-id="${commentId}" data-action="show">
                            <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>
                            Ver ${repliesCount} respuestas
                        </button>
                    </div>`;
             } else {
                repliesUi = `
                    <div class="component-comment-replies-wrapper" id="replies-wrapper-${commentId}">
                        <div class="component-comment-replies" id="replies-container-${commentId}"></div>
                    </div>`;
             }
        }

        const dateStr = isPending ? 'Justo ahora' : new Date(comment.created_at).toLocaleDateString();
        const activeLike = comment.user_interaction === 'like' ? 'active' : '';
        const activeDislike = comment.user_interaction === 'dislike' ? 'active' : '';
        const likesCount = comment.likes_count > 0 ? comment.likes_count : '';

        const VISUAL_LIMIT = 300;
        const content = comment.content || '';
        let contentHtml = '';
        if (content.length > VISUAL_LIMIT) {
            const shortContent = content.substring(0, VISUAL_LIMIT) + '...';
            contentHtml = `
                <div class="component-comment-text-wrapper">
                    <span class="js-text-short">${shortContent}</span>
                    <span class="js-text-full" style="display:none;">${content}</span>
                    <button class="component-button text small js-read-more" data-action="expand" style="padding:0; margin-left:4px; height:auto; display:inline-block; color: var(--text-secondary);">Leer más</button>
                </div>`;
        } else {
            contentHtml = `<div class="component-comment-text">${content}</div>`;
        }

        return `
        <div class="component-comment-item ${isReply ? 'is-reply' : ''}" id="comment-${commentId}" style="${opacityStyle}">
            <a href="/ProjectAurora/channel/${comment.user_uuid}" class="component-comment-avatar-link">
                <img src="${comment.avatar_url || comment.user_avatar}" alt="${comment.username}" class="component-comment-avatar">
            </a>
            <div class="component-comment-content">
                <div class="component-comment-header">
                    <a href="/ProjectAurora/channel/${comment.user_uuid}" class="component-comment-author">${comment.username}</a>
                    <span class="component-comment-date">${dateStr}</span>
                    ${pendingBadge}
                </div>
                ${contentHtml}
                <div class="component-comment-actions">
                    <div class="component-watch-joined-pill comment-size">
                        <button class="component-watch-joined-btn like js-like-trigger ${activeLike}" data-id="${commentId}" title="Me gusta" ${isPending ? 'disabled' : ''}>
                            <span class="material-symbols-rounded" style="font-size: 16px;">thumb_up</span>
                            <span class="count-label js-count-like" style="font-size: 12px;">${likesCount}</span>
                        </button>
                        <div class="component-watch-joined-separator"></div>
                        <button class="component-watch-joined-btn dislike js-dislike-trigger ${activeDislike}" data-id="${commentId}" title="No me gusta" ${isPending ? 'disabled' : ''}>
                            <span class="material-symbols-rounded" style="font-size: 16px;">thumb_down</span>
                        </button>
                    </div>
                    ${isLogged && !isReply ? `
                        <button class="component-watch-action-pill comment-size js-reply-trigger" data-id="${commentId}" style="margin-left: 8px;" ${isPending ? 'disabled' : ''}>
                            Responder
                        </button>
                    ` : ''}
                </div>
                <div id="reply-box-container-${commentId}" class="component-reply-box-container"></div>
                ${repliesUi}
            </div>
        </div>`;
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
        const MAX_CHARS = 10000;
        
        container.innerHTML = `
            <div class="component-watch-comment-input-row reply-mode">
                <img src="${myAvatar}" class="component-watch-avatar small">
                <div class="component-watch-comment-input-wrapper chat-style" style="position:relative;">
                    <textarea id="reply-input-${commentId}" class="component-input auto-expand chat-input" placeholder="Añade una respuesta..." rows="1" maxlength="${MAX_CHARS}"></textarea>
                    
                    <button class="component-input-embedded-btn js-submit-reply" disabled title="Responder">
                        <span class="material-symbols-rounded">send</span>
                    </button>
                </div>
            </div>
        `;

        const input = document.getElementById(`reply-input-${commentId}`);
        const btnSubmit = container.querySelector('.js-submit-reply');
        
        const wrapper = input.parentElement;

        if (wrapper) {
            this._attachExpandLogic(input, wrapper);
        }

        input.addEventListener('input', () => {
            const len = input.value.length;
            const isEmpty = input.value.trim().length === 0;
            btnSubmit.disabled = isEmpty || len > MAX_CHARS;
        });

        btnSubmit.addEventListener('click', () => {
            const text = input.value;
            container.innerHTML = ''; 
            
            this.postComment(text, commentId, null, () => {
                ToastManager.show('Fallo al responder, intenta de nuevo', 'error');
            });
        });
    }

    async toggleInteraction(commentId, type) {
        if (!this.currentUserAvatar) {
            ToastManager.show('Debes iniciar sesión', 'info');
            return;
        }

        const commentEl = document.getElementById(`comment-${commentId}`);
        if (!commentEl) return;
        const btnLike = commentEl.querySelector('.js-like-trigger');
        const btnDislike = commentEl.querySelector('.js-dislike-trigger');
        
        try {
            const response = await ApiService.post(ApiRoutes.Interaction.CommentLike, { comment_id: commentId, type: type });
            if (response.success) {
                btnLike.classList.remove('active');
                btnDislike.classList.remove('active');
                const labelLike = btnLike.querySelector('.count-label');
                labelLike.textContent = response.likes > 0 ? response.likes : '';
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

    async postComment(content, parentId = null, onSuccess, onError) {
        if (this.state.isLoading && !parentId) return;

        const tempId = 'temp-' + Date.now();
        const optimisticComment = {
            id: tempId,
            uuid: tempId,
            video_uuid: this.videoUuid,
            username: this.currentUserName, 
            user_uuid: this.currentUserUuid,
            avatar_url: this.currentUserAvatar,
            content: content,
            parent_id: parentId,
            created_at: new Date().toISOString(),
            likes_count: 0,
            user_interaction: null,
            replies: [],
            is_pending: true 
        };

        this.injectNewComment(optimisticComment);
        if (onSuccess) onSuccess();

        try {
            const response = await ApiService.post(ApiRoutes.Interaction.PostComment, {
                video_uuid: this.videoUuid,
                content: content,
                parent_id: parentId
            });

            if (response.success) {
                const realComment = response.comment;
                
                const tempEl = document.getElementById(`comment-${tempId}`);
                if (tempEl) {
                    tempEl.id = `comment-${realComment.uuid}`; 
                    tempEl.classList.remove('opacity-60'); 
                    tempEl.style.opacity = '1';
                    
                    const badge = tempEl.querySelector('.js-pending-label');
                    if(badge) badge.remove();

                    const btns = tempEl.querySelectorAll(`[data-id="${tempId}"]`);
                    btns.forEach(b => {
                        b.dataset.id = realComment.uuid;
                        b.disabled = false;
                    });
                }
                
                if (parentId) {
                    let replies = this.repliesStore.get(parentId); 
                    if (replies) {
                         const idx = replies.findIndex(r => r.id === tempId);
                         if (idx !== -1) replies[idx] = realComment;
                    }
                }

                ToastManager.show('Comentario publicado', 'success');
            } else {
                throw new Error(response.message);
            }
        } catch (e) {
            console.error("Post comment error:", e);
            
            const tempEl = document.getElementById(`comment-${tempId}`);
            if (tempEl) tempEl.remove();
            
            if (!parentId) this.state.total--;
            
            ToastManager.show(e.message || 'Error de conexión', 'error');
            if (onError) onError();
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
        } else {
            const parentId = commentData.parent_id; 
            let wrapper = document.getElementById(`replies-wrapper-${parentId}`);
            let repliesContainer = document.getElementById(`replies-container-${parentId}`);
            
            if (!wrapper) {
                const parentNode = document.getElementById(`comment-${parentId}`);
                if (parentNode) {
                    const contentDiv = parentNode.querySelector('.component-comment-content');
                    wrapper = document.createElement('div');
                    wrapper.className = 'component-comment-replies-wrapper';
                    wrapper.id = `replies-wrapper-${parentId}`;
                    repliesContainer = document.createElement('div');
                    repliesContainer.className = 'component-comment-replies';
                    repliesContainer.id = `replies-container-${parentId}`;
                    wrapper.appendChild(repliesContainer);
                    contentDiv.appendChild(wrapper);
                }
            }

            if (repliesContainer) {
                const html = this.renderCommentItem(commentData, true);
                repliesContainer.insertAdjacentHTML('beforeend', html);
                
                let storedReplies = this.repliesStore.get(parentId);
                if (!storedReplies) {
                    storedReplies = [];
                    this.repliesStore.set(parentId, storedReplies);
                }
                storedReplies.push(commentData);

                let toggleBtn = wrapper.querySelector(`.js-toggle-replies`);
                if (!toggleBtn) {
                    const btnHtml = `
                        <button class="component-watch-replies-toggle js-toggle-replies" data-id="${parentId}" data-action="show">
                            <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>
                            Ver ${storedReplies.length} respuestas
                        </button>`;
                    wrapper.insertAdjacentHTML('beforeend', btnHtml);
                } else {
                    if (toggleBtn.dataset.action === 'show') {
                         toggleBtn.innerHTML = `<span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>Ver ${storedReplies.length} respuestas`;
                    }
                }
            }
        }
    }
}