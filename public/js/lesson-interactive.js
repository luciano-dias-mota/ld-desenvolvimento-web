(function () {
    'use strict';

    const root = document.querySelector('[data-lesson-interactive]');
    if (!root) {
        return;
    }

    const slug = root.dataset.lessonSlug || '';
    const source = root.dataset.challengeSrc || '';

    if (!slug || !source) {
        return;
    }

    initReadingProgress();

    fetch(source, { credentials: 'same-origin', cache: 'no-cache' })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Não foi possível carregar o desafio desta aula.');
            }
            return response.json();
        })
        .then((data) => {
            const challenge = data[slug];
            if (!challenge) {
                throw new Error('Desafio não encontrado para esta aula.');
            }
            renderExperience(challenge);
        })
        .catch((error) => {
            root.replaceChildren(
                element('div', 'interactive-error', error.message)
            );
        });

    function initReadingProgress() {
        const content = document.querySelector('.lesson-content:not(.lesson-video)');
        const bar = document.querySelector('[data-reading-bar]');
        const label = document.querySelector('[data-reading-label]');

        if (!content || !bar || !label) {
            return;
        }

        const update = () => {
            const rect = content.getBoundingClientRect();
            const viewport = window.innerHeight || document.documentElement.clientHeight;
            const total = Math.max(1, rect.height + viewport * 0.35);
            const travelled = Math.min(total, Math.max(0, viewport * 0.35 - rect.top));
            const percent = Math.max(0, Math.min(100, Math.round((travelled / total) * 100)));

            bar.style.width = percent + '%';
            label.textContent = percent >= 100
                ? '✓ Leitura percorrida'
                : 'Leitura da aula: ' + percent + '%';
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
    }

    function renderExperience(challenge) {
        const fragment = document.createDocumentFragment();

        fragment.appendChild(renderVisual(challenge));
        fragment.appendChild(renderChallenge(challenge));

        root.replaceChildren(fragment);
    }

    function renderVisual(challenge) {
        const section = element('section', 'concept-visual');
        const header = element('div', 'concept-visual-header');
        header.appendChild(element('span', 'interactive-kicker', 'VISUAL MODE'));
        header.appendChild(element('h2', '', '🗺️ Visualize o conceito'));
        header.appendChild(
            element(
                'p',
                'text-muted',
                'Use este mapa como uma fotografia mental antes de voltar ao código.'
            )
        );
        section.appendChild(header);

        const flow = element('div', 'concept-flow');
        const steps = Array.isArray(challenge.visual_steps) ? challenge.visual_steps : [];

        steps.forEach((step, index) => {
            const node = element('div', 'concept-node');
            node.appendChild(element('span', 'concept-node-number', String(index + 1).padStart(2, '0')));
            node.appendChild(element('strong', '', String(step)));
            flow.appendChild(node);

            if (index < steps.length - 1) {
                flow.appendChild(element('span', 'concept-arrow', '→'));
            }
        });

        section.appendChild(flow);
        return section;
    }

    function renderChallenge(challenge) {
        const section = element('section', 'interactive-lab');
        section.dataset.challenge = 'lesson';

        const heading = element('div', 'interactive-heading');
        const headingText = element('div');
        headingText.appendChild(element('span', 'interactive-kicker', 'PRACTICE MODE'));
        headingText.appendChild(element('h2', '', challenge.title || '⚡ Desafio Relâmpago'));
        headingText.appendChild(
            element(
                'p',
                '',
                challenge.prompt || 'Complete o trecho e verifique sua resposta.'
            )
        );
        heading.appendChild(headingText);
        heading.appendChild(element('span', 'interactive-badge', 'treino extra · sem XP'));
        section.appendChild(heading);

        const editorShell = element('div', 'mini-editor');
        const toolbar = element('div', 'mini-editor-toolbar');
        const dots = element('div', 'mini-editor-dots');
        dots.appendChild(element('span'));
        dots.appendChild(element('span'));
        dots.appendChild(element('span'));
        toolbar.appendChild(dots);
        toolbar.appendChild(element('span', 'mini-editor-title', 'mini-lab'));
        toolbar.appendChild(element('span', 'mini-editor-shortcut', 'Ctrl + Enter'));
        editorShell.appendChild(toolbar);

        const editor = document.createElement('textarea');
        editor.className = 'mini-editor-input';
        editor.spellcheck = false;
        editor.setAttribute('aria-label', 'Editor do desafio relâmpago');
        editor.value = String(challenge.starter || '');
        editorShell.appendChild(editor);
        section.appendChild(editorShell);

        const actions = element('div', 'interactive-actions');
        const verifyButton = button('Verificar código', 'btn btn-primary');
        const hintButton = button('💡 Dica', 'btn btn-outline');
        const solutionButton = button('👁 Ver solução', 'btn btn-outline');
        const resetButton = button('↺ Restaurar', 'btn btn-ghost');

        actions.append(verifyButton, hintButton, solutionButton, resetButton);
        section.appendChild(actions);

        const hint = element('div', 'interactive-note');
        hint.hidden = true;
        hint.appendChild(element('strong', '', 'Dica: '));
        hint.appendChild(document.createTextNode(String(challenge.hint || 'Revise o exemplo da aula.')));
        section.appendChild(hint);

        const result = element('div', 'interactive-result');
        result.hidden = true;
        result.setAttribute('role', 'status');
        result.setAttribute('aria-live', 'polite');
        section.appendChild(result);

        const solution = element('div', 'interactive-solution');
        solution.hidden = true;
        solution.appendChild(element('strong', '', 'Uma solução possível'));
        const solutionPre = document.createElement('pre');
        const solutionCode = document.createElement('code');
        solutionCode.textContent = String(challenge.solution || '');
        solutionPre.appendChild(solutionCode);
        solution.appendChild(solutionPre);
        section.appendChild(solution);

        const storageKey = 'ldweb-mini-challenge:' + slug;
        if (safeStorageGet(storageKey) === 'done') {
            result.hidden = false;
            result.className = 'interactive-result is-success';
            result.textContent = '✓ Você já concluiu este treino neste navegador. Pode refazê-lo quando quiser.';
        }

        verifyButton.addEventListener('click', () => verify(editor, result, challenge, storageKey));

        hintButton.addEventListener('click', () => {
            hint.hidden = !hint.hidden;
            hintButton.textContent = hint.hidden ? '💡 Dica' : 'Ocultar dica';
        });

        solutionButton.addEventListener('click', () => {
            solution.hidden = !solution.hidden;
            solutionButton.textContent = solution.hidden ? '👁 Ver solução' : 'Ocultar solução';
        });

        resetButton.addEventListener('click', () => {
            editor.value = String(challenge.starter || '');
            result.hidden = true;
            editor.focus();
        });

        editor.addEventListener('keydown', (event) => {
            if (event.ctrlKey && event.key === 'Enter') {
                event.preventDefault();
                verify(editor, result, challenge, storageKey);
            }
        });

        return section;
    }

    function verify(editor, result, challenge, storageKey) {
        const candidate = String(editor.value || '');
        const required = Array.isArray(challenge.required) ? challenge.required : [];
        const normalizedCandidate = normalize(candidate);

        if (candidate.includes('___')) {
            showResult(
                result,
                false,
                'Ainda existe um ___ no editor. Substitua o trecho faltante antes de verificar.'
            );
            return;
        }

        const missing = required.filter((part) => !normalizedCandidate.includes(normalize(part)));

        if (missing.length > 0) {
            showResult(
                result,
                false,
                'Ainda não. O trecho principal não corresponde ao conceito esperado. Use a dica, compare com a explicação e tente novamente.'
            );
            return;
        }

        safeStorageSet(storageKey, 'done');
        showResult(
            result,
            true,
            '✓ ' + String(challenge.success || 'Desafio concluído!')
        );
    }

    function showResult(target, success, message) {
        target.hidden = false;
        target.className = 'interactive-result ' + (success ? 'is-success' : 'is-error');
        target.textContent = message;
    }

    function normalize(value) {
        return String(value)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, '')
            .toLowerCase();
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function button(text, className) {
        const node = element('button', className, text);
        node.type = 'button';
        return node;
    }

    function safeStorageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function safeStorageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // O treino continua funcionando mesmo sem armazenamento local.
        }
    }
})();
