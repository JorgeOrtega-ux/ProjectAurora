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

        // Almacén para guardar las respuestas de cada comentario (ID -> Array de respuestas)
        this.repliesStore = new Map();
        
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
        const counter = document.getElementById('main-comment-counter');
        const MAX_CHARS = 10000;
        
        if (mainInput && mainBtn) {
            mainInput.addEventListener('input', () => {
                const currentLength = mainInput.value.length;
                const isEmpty = mainInput.value.trim().length === 0;
                
                // 1. Gestión del botón enviar
                mainBtn.disabled = isEmpty || currentLength > MAX_CHARS;
                
                // 2. Auto-resize
                mainInput.style.height = 'auto';
                mainInput.style.height = mainInput.scrollHeight + 'px';

                // 3. Actualizar contador
                if (counter) {
                    counter.textContent = `${currentLength.toLocaleString()} / ${MAX_CHARS.toLocaleString()}`;
                    if (currentLength > MAX_CHARS) {
                        counter.style.color = 'var(--color-error, #ff4444)';
                    } else {
                        counter.style.color = 'var(--text-tertiary)';
                    }
                }
            });

            mainBtn.addEventListener('click', () => {
                this.postComment(mainInput.value, null, () => {
                    mainInput.value = '';
                    mainInput.style.height = 'auto';
                    mainBtn.disabled = true;
                    if (counter) counter.textContent = `0 / ${MAX_CHARS.toLocaleString()}`;
                });
            });
            
            // Opcional: Ctrl+Enter para enviar
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
                // 1. Responder
                const replyBtn = e.target.closest('.js-reply-trigger');
                if (replyBtn) {
                    const commentId = replyBtn.dataset.id;
                    this.toggleReplyBox(commentId);
                    return;
                }

                // 2. Like
                const likeBtn = e.target.closest('.js-like-trigger');
                if (likeBtn) {
                    const commentId = likeBtn.dataset.id;
                    this.toggleInteraction(commentId, 'like');
                    return;
                }

                // 3. Dislike
                const dislikeBtn = e.target.closest('.js-dislike-trigger');
                if (dislikeBtn) {
                    const commentId = dislikeBtn.dataset.id;
                    this.toggleInteraction(commentId, 'dislike');
                    return;
                }

                // 4. MOSTRAR/OCULTAR RESPUESTAS
                const toggleRepliesBtn = e.target.closest('.js-toggle-replies');
                if (toggleRepliesBtn) {
                    const commentId = toggleRepliesBtn.dataset.id;
                    this.handleRepliesToggle(commentId, toggleRepliesBtn);
                    return;
                }

                // 5. LEER MÁS / LEER MENOS (Texto Largo)
                const readMoreBtn = e.target.closest('.js-read-more');
                if (readMoreBtn) {
                    const container = readMoreBtn.closest('.component-comment-text-wrapper');
                    const shortText = container.querySelector('.js-text-short');
                    const fullText = container.querySelector('.js-text-full');
                    const action = readMoreBtn.dataset.action; // 'expand' o 'collapse'

                    if (action === 'expand') {
                        shortText.style.display = 'none';
                        fullText.style.display = 'inline';
                        readMoreBtn.textContent = 'Leer menos';
                        readMoreBtn.dataset.action = 'collapse';
                    } else {
                        shortText.style.display = 'inline';
                        fullText.style.display = 'none';
                        readMoreBtn.textContent = 'Leer más';
                        readMoreBtn.dataset.action = 'expand';
                    }
                    return;
                }
            });
        }
    }

    handleRepliesToggle(commentId, btnElement) {
        const container = document.getElementById(`replies-container-${commentId}`);
        const replies = this.repliesStore.get(parseInt(commentId)) || [];
        
        const isHiding = btnElement.dataset.action === 'hide';
        
        if (isHiding) {
            // Acción: OCULTAR -> Vaciamos contenedor
            container.innerHTML = '';
            btnElement.dataset.action = 'show';
            btnElement.innerHTML = `
                <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>
                Ver ${replies.length} respuestas
            `;
            return;
        }

        // Acción: MOSTRAR -> Renderizamos lote
        const currentCount = container.children.length;
        const BATCH_SIZE = 10;
        const nextBatch = replies.slice(currentCount, currentCount + BATCH_SIZE);

        nextBatch.forEach(reply => {
            const html = this.renderCommentItem(reply, true);
            container.insertAdjacentHTML('beforeend', html);
        });

        const newTotalShown = currentCount + nextBatch.length;
        
        if (newTotalShown >= replies.length) {
            // Se mostraron todas -> Botón cambia a Ocultar
            btnElement.dataset.action = 'hide';
            btnElement.innerHTML = `
                <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_less</span>
                Ocultar respuestas
            `;
        } else {
            // Quedan más -> Botón Mostrar más
            btnElement.dataset.action = 'show';
            btnElement.innerHTML = `
                <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">subdirectory_arrow_right</span>
                Mostrar más
            `;
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
        
        let repliesUi = '';
        const repliesCount = comment.replies ? comment.replies.length : 0;

        if (!isReply && repliesCount > 0) {
            this.repliesStore.set(comment.id, comment.replies);

            repliesUi = `
                <div class="component-comment-replies-wrapper">
                    <div class="component-comment-replies" id="replies-container-${comment.id}"></div>
                    
                    <button class="component-watch-replies-toggle js-toggle-replies" data-id="${comment.id}" data-action="show">
                        <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>
                        Ver ${repliesCount} respuestas
                    </button>
                </div>
            `;
        } else if (!isReply) {
            this.repliesStore.set(comment.id, []); 
            repliesUi = `
                <div class="component-comment-replies-wrapper" id="replies-wrapper-${comment.id}">
                    <div class="component-comment-replies" id="replies-container-${comment.id}"></div>
                    </div>`;
        }

        const dateStr = new Date(comment.created_at).toLocaleDateString();
        const activeLike = comment.user_interaction === 'like' ? 'active' : '';
        const activeDislike = comment.user_interaction === 'dislike' ? 'active' : '';
        const likesCount = comment.likes_count > 0 ? comment.likes_count : '';

        // --- LÓGICA DE TRUNCADO DE TEXTO ---
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
                </div>
            `;
        } else {
            contentHtml = `<div class="component-comment-text">${content}</div>`;
        }
        // -----------------------------------

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
                
                ${contentHtml}
                
                <div class="component-comment-actions">
                    <div class="component-watch-joined-pill comment-size">
                        <button class="component-watch-joined-btn like js-like-trigger ${activeLike}" data-id="${comment.id}" title="Me gusta">
                            <span class="material-symbols-rounded" style="font-size: 16px;">thumb_up</span>
                            <span class="count-label js-count-like" style="font-size: 12px;">${likesCount}</span>
                        </button>
                        <div class="component-watch-joined-separator"></div>
                        <button class="component-watch-joined-btn dislike js-dislike-trigger ${activeDislike}" data-id="${comment.id}" title="No me gusta">
                            <span class="material-symbols-rounded" style="font-size: 16px;">thumb_down</span>
                        </button>
                    </div>

                    ${isLogged && !isReply ? `
                        <button class="component-button text small js-reply-trigger" data-id="${comment.id}" style="margin-left: 8px;">
                            Responder
                        </button>
                    ` : ''}
                </div>

                <div id="reply-box-container-${comment.id}" class="component-reply-box-container"></div>

                ${repliesUi}
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
        const MAX_CHARS = 10000;
        
        container.innerHTML = `
            <div class="component-watch-comment-input-row reply-mode">
                <img src="${myAvatar}" class="component-watch-avatar small">
                <div class="component-watch-comment-input-wrapper chat-style" style="position:relative;">
                    <textarea id="reply-input-${commentId}" class="component-input auto-expand chat-input" placeholder="Añade una respuesta..." rows="1" maxlength="${MAX_CHARS}"></textarea>
                    
                    <span id="reply-counter-${commentId}" style="position: absolute; bottom: -18px; right: 0; font-size: 11px; color: var(--text-tertiary);">0 / ${MAX_CHARS.toLocaleString()}</span>

                    <button class="component-input-embedded-btn js-submit-reply" disabled title="Responder">
                        <span class="material-symbols-rounded">send</span>
                    </button>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:8px; margin-bottom:12px;">
                 <button class="component-button text small js-cancel-reply">Cancelar</button>
            </div>
        `;

        const input = document.getElementById(`reply-input-${commentId}`);
        const btnSubmit = container.querySelector('.js-submit-reply');
        const btnCancel = container.querySelector('.js-cancel-reply');
        const counter = document.getElementById(`reply-counter-${commentId}`);

        input.focus();

        input.addEventListener('input', () => {
            const len = input.value.length;
            const isEmpty = input.value.trim().length === 0;

            btnSubmit.disabled = isEmpty || len > MAX_CHARS;
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';

            if (counter) {
                counter.textContent = `${len.toLocaleString()} / ${MAX_CHARS.toLocaleString()}`;
                if (len > MAX_CHARS) {
                    counter.style.color = 'var(--color-error, #ff4444)';
                } else {
                    counter.style.color = 'var(--text-tertiary)';
                }
            }
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
            const response = await ApiService.post(ApiRoutes.Interaction.CommentLike, {
                comment_id: commentId,
                type: type
            });

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
            // Lógica para inyectar respuesta
            let wrapper = document.getElementById(`replies-wrapper-${parentId}`);
            let repliesContainer = document.getElementById(`replies-container-${parentId}`);
            
            // Si no existe el wrapper (porque era un comentario sin respuestas), lo creamos
            if (!wrapper) {
                const parentNode = document.getElementById(`comment-${parentId}`);
                if (parentNode) {
                    const contentDiv = parentNode.querySelector('.component-comment-content');
                    
                    wrapper = document.createElement('div');
                    wrapper.className = 'component-comment-replies-wrapper';
                    wrapper.id = `replies-wrapper-${parentId}`;
                    
                    // IMPORTANTE: El orden de creación aquí también importa
                    repliesContainer = document.createElement('div');
                    repliesContainer.className = 'component-comment-replies';
                    repliesContainer.id = `replies-container-${parentId}`;
                    
                    wrapper.appendChild(repliesContainer); // 1. Contenedor
                    contentDiv.appendChild(wrapper);
                }
            }

            if (repliesContainer) {
                // Inyectamos la nueva respuesta
                const html = this.renderCommentItem(commentData, true);
                repliesContainer.insertAdjacentHTML('beforeend', html);
                
                // Actualizamos store
                let storedReplies = this.repliesStore.get(parseInt(parentId));
                if (!storedReplies) {
                    storedReplies = [];
                    this.repliesStore.set(parseInt(parentId), storedReplies);
                }
                storedReplies.push(commentData);

                // Gestionamos el botón que debe ir DEBAJO
                // Buscamos si ya existe el botón dentro del wrapper
                let toggleBtn = wrapper.querySelector(`.js-toggle-replies`);

                if (!toggleBtn) {
                    // Si no existe, lo creamos y lo ponemos AL FINAL del wrapper (debajo del container)
                    const btnHtml = `
                        <button class="component-watch-replies-toggle js-toggle-replies" data-id="${parentId}" data-action="show">
                            <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>
                            Ver ${storedReplies.length} respuestas
                        </button>
                    `;
                    wrapper.insertAdjacentHTML('beforeend', btnHtml);
                } else {
                    // Si ya existe, actualizamos el texto
                    if (toggleBtn.dataset.action === 'show') {
                         toggleBtn.innerHTML = `
                            <span class="material-symbols-rounded" style="font-size: 18px; margin-right:6px;">expand_more</span>
                            Ver ${storedReplies.length} respuestas
                        `;
                    }
                }
            }
        }
    }
}