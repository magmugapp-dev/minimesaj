<style>
    html {
        scroll-behavior: smooth;
        scrollbar-color: rgba(148, 163, 184, .6) transparent;
    }

    .studio {
        position: relative;
        isolation: isolate;
        --studio-accent: #4f46e5;
        --studio-accent-rgb: 79, 70, 229;
        --studio-accent-soft: #eef2ff;
        --studio-glow: rgba(99, 102, 241, .2);
    }

    .studio--influencer {
        --studio-accent: #c026d3;
        --studio-accent-rgb: 192, 38, 211;
        --studio-accent-soft: #fdf4ff;
        --studio-glow: rgba(244, 114, 182, .22);
    }

    .studio--ai {
        --studio-accent: #0f766e;
        --studio-accent-rgb: 15, 118, 110;
        --studio-accent-soft: #ccfbf1;
        --studio-glow: rgba(45, 212, 191, .2);
    }

    .studio::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        /* border-radius: 40px; */
        background: radial-gradient(circle at top right, var(--studio-glow), transparent 34%), radial-gradient(circle at bottom left, rgba(15, 23, 42, .07), transparent 28%);
        pointer-events: none;
    }

    .studio-main,
    .studio-sidebar,
    .studio-grid,
    .studio-meta,
    .studio-nav,
    .studio-form-grid,
    .studio-choice-grid,
    .studio-toggle-grid,
    .studio-slider-grid,
    .studio-actions__buttons {
        display: grid;
        gap: 1rem;
    }

    .studio-back {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        border-radius: 999px;
        border: 1px solid rgba(var(--studio-accent-rgb), .16);
        background: rgba(var(--studio-accent-rgb), .08);
        padding: .7rem 1rem;
        font-size: .9rem;
        font-weight: 600;
        color: #0f172a;
        transition: background-color .18s ease, color .18s ease, transform .18s ease;
    }

    .studio-back:hover {
        background: rgba(var(--studio-accent-rgb), .14);
        color: var(--studio-accent);
        transform: translateY(-1px);
    }

    .studio-kicker,
    .studio-meta__eyebrow {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .22em;
        text-transform: uppercase;
    }

    .studio-grid {
        margin-top: 1.75rem;
    }

    .studio-card {
        border-radius: 30px;
        border: 1px solid rgba(226, 232, 240, .9);
        background: rgba(255, 255, 255, .93);
        padding: clamp(1.2rem, 2vw, 2rem);
        backdrop-filter: blur(18px);
        box-shadow: 0 24px 46px -36px rgba(15, 23, 42, .38);
    }

    .studio-card__header {
        display: flex;
        flex-direction: column;
        gap: .85rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .studio-kicker,
    .studio-meta__eyebrow {
        color: #64748b;
    }

    .studio-title {
        margin-top: .5rem;
        font-size: clamp(1.45rem, 2vw, 2rem);
        line-height: 1.04;
        font-weight: 700;
        letter-spacing: -.04em;
        color: #020617;
    }

    .studio-label,
    .studio-choice__title,
    .studio-toggle__title,
    .studio-slider__title,
    .studio-meta__value {
        color: #0f172a;
        font-weight: 650;
    }

    .studio-label {
        display: block;
        font-size: .9rem;
    }

    .studio-hint {
        margin-top: .5rem;
        font-size: .78rem;
        line-height: 1.6;
        color: #64748b;
    }

    .studio-input,
    .studio-select,
    .studio-textarea {
        width: 100%;
        margin-top: .55rem;
        border-radius: 22px;
        border: 1px solid #d4dbe5;
        background: linear-gradient(180deg, #fff, #f8fafc);
        padding: .95rem 1rem;
        font-size: .96rem;
        line-height: 1.45;
        color: #0f172a;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .68), 0 1px 2px rgba(15, 23, 42, .04);
        transition: border-color .18s ease, box-shadow .18s ease;
    }

    .studio-input:focus,
    .studio-select:focus,
    .studio-textarea:focus {
        outline: none;
        border-color: var(--studio-accent);
        box-shadow: 0 0 0 4px rgba(var(--studio-accent-rgb), .12), 0 22px 38px -30px rgba(var(--studio-accent-rgb), .7);
    }

    .studio-input::placeholder,
    .studio-textarea::placeholder {
        color: #94a3b8;
        opacity: 1;
    }

    .studio-textarea {
        min-height: 7.5rem;
        resize: vertical;
    }

    .studio-choice {
        position: relative;
        display: block;
        cursor: pointer;
    }

    .studio-choice input {
        position: absolute;
        inset: 0;
        opacity: 0;
        pointer-events: none;
    }

    .studio-choice__card,
    .studio-toggle,
    .studio-slider,
    .studio-meta__item,
    .studio-nav__link,
    .studio-linkcard {
        border-radius: 26px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fff, #f8fafc);
    }

    .studio-choice__card {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: .85rem;
        width: 100%;
        height: 100%;
        padding: 1.2rem;
        box-shadow: 0 14px 24px -22px rgba(15, 23, 42, .45);
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, background .18s ease;
    }

    .studio-choice:hover .studio-choice__card,
    .studio-nav__link:hover,
    .studio-linkcard:hover {
        transform: translateY(-1px);
        border-color: rgba(var(--studio-accent-rgb), .32);
    }

    .studio-choice input:checked+.studio-choice__card {
        border-color: rgba(var(--studio-accent-rgb), .5);
        background: linear-gradient(180deg, rgba(var(--studio-accent-rgb), .1), rgba(255, 255, 255, .98));
        box-shadow: 0 24px 40px -30px rgba(var(--studio-accent-rgb), .75);
    }

    .studio-choice__pill,
    .studio-slider__value {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: var(--studio-accent-soft);
        color: var(--studio-accent);
    }

    .studio-choice__pill {
        padding: .42rem .8rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .studio-choice__title {
        display: block;
        margin-top: 0;
        font-size: 1.08rem;
        font-weight: 700;
        color: #020617;
    }

    .studio-toggle,
    .studio-slider,
    .studio-meta__item {
        padding: 1.1rem;
        box-shadow: 0 16px 26px -28px rgba(15, 23, 42, .42);
    }

    .studio-toggle__row,
    .studio-slider__top,
    .studio-actions {
        display: flex;
        gap: 1rem;
        justify-content: space-between;
    }

    .studio-check {
        margin-top: .12rem;
        height: 1.15rem;
        width: 1.15rem;
        accent-color: var(--studio-accent);
    }

    .studio-slider__value {
        min-width: 2.6rem;
        padding: .68rem .8rem;
        font-size: .92rem;
        font-weight: 700;
        border-radius: 18px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .76);
    }

    .studio-range {
        width: 100%;
        margin-top: 1rem;
        accent-color: var(--studio-accent);
    }

    .studio-range__legend {
        margin-top: .55rem;
        display: flex;
        justify-content: space-between;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .studio-nav__link,
    .studio-linkcard {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .95rem 1rem;
        font-size: .92rem;
        font-weight: 650;
        color: #334155;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, color .18s ease;
    }

    .studio-nav__link:hover {
        color: var(--studio-accent);
        box-shadow: 0 18px 28px -26px rgba(var(--studio-accent-rgb), .7);
    }

    .studio-linkcard:hover {
        box-shadow: 0 18px 30px -24px rgba(var(--studio-accent-rgb), .56);
    }

    .studio-meta__value {
        margin-top: .55rem;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.35;
        color: #020617;
    }

    .studio-pill-list {
        display: flex;
        flex-wrap: wrap;
        gap: .65rem;
    }

    .studio-pill {

        display: inline-flex;
        align-items: center;
        gap: .45rem;
        border-radius: 999px;
        padding: .55rem .82rem;
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .studio-stack,
    .studio-stat-grid,
    .studio-info-grid,
    .studio-progress-list {
        display: grid;
        gap: 1rem;
    }

    .studio-notice {
        margin-top: 1rem;
        border-radius: 24px;
        border: 1px solid #dbe3ee;
        padding: 1rem 1.15rem;
        font-size: .92rem;
        font-weight: 650;
        line-height: 1.6;
        box-shadow: 0 20px 34px -34px rgba(15, 23, 42, .35);
    }

    .studio-notice--success {
        border-color: #a7f3d0;
        background: linear-gradient(180deg, #ecfdf5, #f7fff9);
        color: #047857;
    }

    .studio-notice--warning {
        border-color: #fde68a;
        background: linear-gradient(180deg, #fffbeb, #fffef8);
        color: #b45309;
    }

    .studio-stat {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fff, #f8fafc);
        padding: 1.15rem;
        box-shadow: 0 22px 38px -34px rgba(15, 23, 42, .36);
    }

    .studio-stat::after {
        content: '';
        position: absolute;
        inset: auto -12% -48% auto;
        width: 8.5rem;
        aspect-ratio: 1;
        border-radius: 999px;
        background: rgba(var(--studio-accent-rgb), .1);
    }

    .studio-stat__label {
        position: relative;
        z-index: 1;
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: #64748b;
    }

    .studio-stat__value {
        position: relative;
        z-index: 1;
        margin-top: .55rem;
        font-size: clamp(1.8rem, 3vw, 2.35rem);
        line-height: .95;
        font-weight: 780;
        letter-spacing: -.05em;
        color: #020617;
    }

    .studio-surface {
        border-radius: 26px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fff, #f8fafc);
        padding: 1.05rem 1.1rem;
        box-shadow: 0 18px 30px -32px rgba(15, 23, 42, .36);
    }

    .studio-surface__title {
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: #64748b;
    }

    .studio-data-list {
        display: grid;
        gap: .85rem;
        margin-top: 1rem;
    }

    .studio-data-row {
        display: grid;
        gap: .35rem;
        padding-bottom: .85rem;
        border-bottom: 1px solid #e8edf4;
    }

    .studio-data-row:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .studio-data-label {
        font-size: .82rem;
        color: #64748b;
    }

    .studio-data-value {
        font-size: .93rem;
        font-weight: 700;
        line-height: 1.5;
        color: #020617;
        word-break: break-word;
    }

    .studio-data-value--code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: .82rem;
        font-weight: 650;
    }

    .studio-copy-block,
    .studio-code {
        margin-top: 1rem;
        border-radius: 22px;
        border: 1px solid #e2e8f0;
        padding: 1rem;
        line-height: 1.72;
        white-space: pre-wrap;
    }

    .studio-copy-block {
        background: #f8fafc;
        font-size: .92rem;
        color: #334155;
    }

    .studio-code {
        max-height: 18rem;
        overflow: auto;
        background: #0f172a;
        font-size: .82rem;
        color: #cbd5e1;
    }

    .studio-pill {
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
    }

    .studio-pill--success {
        border-color: #a7f3d0;
        background: #ecfdf5;
        color: #047857;
    }

    .studio-pill--warning {
        border-color: #fde68a;
        background: #fffbeb;
        color: #b45309;
    }

    .studio-pill--danger {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }

    .studio-pill--info {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .studio-pill--neutral {
        border-color: #dbe3ee;
        background: #f8fafc;
        color: #475569;
    }

    .studio-progress {
        display: grid;
        gap: .45rem;
    }

    .studio-progress__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .studio-progress__label {
        font-size: .88rem;
        font-weight: 650;
        color: #334155;
    }

    .studio-progress__value {
        font-size: .84rem;
        font-weight: 700;
        color: #020617;
    }

    .studio-progress__track {
        width: 100%;
        height: .7rem;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .studio-progress__fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--studio-accent), rgba(var(--studio-accent-rgb), .48));
    }

    .studio-actions__buttons {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .studio-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        border-radius: 22px;
        padding: .92rem 1.4rem;
        font-size: .92rem;
        font-weight: 700;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, filter .18s ease;
    }

    .studio-button:hover {
        transform: translateY(-1px);
    }

    .studio-button--primary {
        background: linear-gradient(180deg, rgba(var(--studio-accent-rgb), .92), var(--studio-accent));
        color: #fff;
        box-shadow: 0 24px 36px -28px rgba(var(--studio-accent-rgb), .92);
    }

    .studio-button--ghost {
        border: 1px solid #dbe3ee;
        background: #fff;
        color: #334155;
        box-shadow: 0 10px 22px -20px rgba(15, 23, 42, .4);
    }

    .studio-alert {
        border-radius: 28px;
        border: 1px solid #fecdd3;
        background: linear-gradient(180deg, #fff1f2, #fff7f7);
        padding: 1.1rem 1.2rem;
        box-shadow: 0 18px 28px -30px rgba(244, 63, 94, .6);
    }

    .studio-alert__row {
        display: flex;
        align-items: flex-start;
        gap: .85rem;
    }

    .studio-alert__icon {
        display: flex;
        height: 2.4rem;
        width: 2.4rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: #ffe4e6;
        color: #e11d48;
    }

    .studio-alert__title {
        font-size: .94rem;
        font-weight: 700;
        color: #881337;
    }

    .studio-alert__copy,
    .studio-alert__list {
        margin-top: .28rem;
        font-size: .88rem;
        line-height: 1.65;
        color: #9f1239;
    }

    .studio-alert__list {
        display: grid;
        gap: .22rem;
        margin-top: .7rem;
    }

    .board,
    .board-top,
    .board-main__stats,
    .board-stack,
    .board-metrics,
    .board-bottom,
    .board-progress-list,
    .board-actions-grid,
    .board-list {
        display: grid;
        gap: 1rem;
    }

    .board {
        gap: 1.1rem;
    }

    .board-top {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .board-main,
    .board-card,
    .board-panel,
    .board-stat,
    .board-progress-card,
    .board-action,
    .board-list__item {
        position: relative;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        box-shadow: 0 20px 40px -34px rgba(15, 23, 42, .4);
    }

    .board-main,
    .board-card,
    .board-panel,
    .board-stat {
        border-radius: 30px;
    }

    .board-main {
        padding: 1.35rem;
        background: linear-gradient(135deg, #0f172a, #1e1b4b 54%, #312e81);
        color: #fff;
        box-shadow: 0 34px 60px -42px rgba(15, 23, 42, .85);
    }

    .board-main::after {
        content: '';
        position: absolute;
        inset: auto -14% -48% auto;
        width: 14rem;
        aspect-ratio: 1;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(255, 255, 255, .22), transparent 62%);
    }

    .board-chipbar {
        display: flex;
        flex-wrap: wrap;
        gap: .6rem;
        position: relative;
        z-index: 1;
    }

    .board-chip {
        display: inline-flex;
        align-items: center;
        gap: .42rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, .14);
        background: rgba(255, 255, 255, .1);
        padding: .52rem .82rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #e2e8f0;
    }

    .board-chip__dot {
        width: .5rem;
        height: .5rem;
        border-radius: 999px;
        background: #22c55e;
    }

    .board-kicker {
        margin-top: 1rem;
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .22em;
        text-transform: uppercase;
        color: rgba(191, 219, 254, .78);
        position: relative;
        z-index: 1;
    }

    .board-title {
        margin-top: .55rem;
        font-size: clamp(1.8rem, 4vw, 2.8rem);
        line-height: .95;
        font-weight: 780;
        letter-spacing: -.05em;
        position: relative;
        z-index: 1;
    }

    .board-total {
        margin-top: 1rem;
        font-size: clamp(2.8rem, 6vw, 4.8rem);
        line-height: .88;
        font-weight: 800;
        letter-spacing: -.08em;
        position: relative;
        z-index: 1;
    }

    .board-subtitle {
        margin-top: .45rem;
        font-size: .9rem;
        color: rgba(226, 232, 240, .76);
        position: relative;
        z-index: 1;
    }

    .board-main__stats {
        margin-top: 1.25rem;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        position: relative;
        z-index: 1;
    }

    .board-mini {
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, .12);
        background: rgba(255, 255, 255, .1);
        padding: .95rem 1rem;
        backdrop-filter: blur(16px);
    }

    .board-mini__label,
    .board-card__label,
    .board-stat__label,
    .board-panel__eyebrow,
    .board-progress__label,
    .board-action__meta,
    .board-list__meta {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .board-mini__label {
        color: rgba(191, 219, 254, .76);
    }

    .board-mini__value {
        margin-top: .42rem;
        font-size: 1.4rem;
        font-weight: 760;
        line-height: 1;
    }

    .board-mini__meta {
        margin-top: .35rem;
        font-size: .82rem;
        color: rgba(226, 232, 240, .72);
    }

    .board-ring-card,
    .board-card {
        padding: 1.15rem;
    }

    .board-ring-card {
        display: grid;
        align-content: center;
        gap: 1rem;
        border-radius: 30px;
        border: 1px solid #dbe3ee;
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        box-shadow: 0 24px 42px -34px rgba(15, 23, 42, .36);
    }

    .board-ring-card__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .board-card__label,
    .board-panel__eyebrow,
    .board-list__meta {
        color: #64748b;
    }

    .board-ring {
        --board-ring: 72;
        --board-ring-color: #22c55e;
        position: relative;
        width: 124px;
        height: 124px;
        margin: 0 auto;
        border-radius: 999px;
        background: conic-gradient(var(--board-ring-color) calc(var(--board-ring) * 1%), #e2e8f0 0);
        display: grid;
        place-items: center;
    }

    .board-ring::after {
        content: '';
        position: absolute;
        inset: 12px;
        border-radius: inherit;
        background: #fff;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .8);
    }

    .board-ring__inner {
        position: relative;
        z-index: 1;
        text-align: center;
    }

    .board-ring__value {
        font-size: 1.35rem;
        font-weight: 780;
        line-height: 1;
        color: #020617;
    }

    .board-ring__meta {
        margin-top: .35rem;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: #64748b;
    }

    .board-ring-card__stats {
        display: grid;
        gap: .7rem;
    }

    .board-ring-card__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-radius: 18px;
        background: #f8fafc;
        padding: .8rem .9rem;
        font-size: .88rem;
        color: #334155;
    }

    .board-ring-card__row strong {
        font-size: 1rem;
        font-weight: 760;
        color: #020617;
    }

    .board-stack {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .board-card::after,
    .board-stat::after {
        content: '';
        position: absolute;
        inset: auto -10% -45% auto;
        width: 10rem;
        aspect-ratio: 1;
        border-radius: 999px;
        background: var(--board-soft, #eef2ff);
        opacity: .58;
    }

    .board-card__value,
    .board-stat__value {
        position: relative;
        z-index: 1;
        margin-top: .55rem;
        font-size: clamp(1.7rem, 3vw, 2.5rem);
        line-height: .95;
        font-weight: 780;
        letter-spacing: -.05em;
        color: #020617;
    }

    .board-card__meta,
    .board-stat__meta {
        position: relative;
        z-index: 1;
        margin-top: .5rem;
        font-size: .84rem;
        color: #64748b;
    }

    .board-card--emerald {
        --board-soft: #dcfce7;
    }

    .board-card--rose {
        --board-soft: #ffe4e6;
    }

    .board-card--amber {
        --board-soft: #fef3c7;
    }

    .board-card__trend {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        margin-top: .9rem;
        border-radius: 999px;
        background: rgba(15, 23, 42, .05);
        padding: .46rem .72rem;
        font-size: .76rem;
        font-weight: 700;
        color: #334155;
    }

    .board-metrics {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .board-stat {
        padding: 1rem 1.05rem;
    }

    .board-stat__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .8rem;
        position: relative;
        z-index: 1;
    }

    .board-stat__icon,
    .board-action__icon,
    .board-list__avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .board-stat__icon {
        width: 2.8rem;
        height: 2.8rem;
        border-radius: 20px;
        background: var(--board-soft, #eef2ff);
        color: var(--board-accent, #4f46e5);
    }

    .board-stat__label {
        color: #64748b;
    }

    .board-stat__meta {
        font-size: .78rem;
    }

    .board-stat--indigo {
        --board-soft: #eef2ff;
        --board-accent: #4f46e5;
    }

    .board-stat--violet {
        --board-soft: #f5f3ff;
        --board-accent: #7c3aed;
    }

    .board-stat--sky {
        --board-soft: #f0f9ff;
        --board-accent: #0284c7;
    }

    .board-stat--emerald {
        --board-soft: #ecfdf5;
        --board-accent: #059669;
    }

    .board-stat--rose {
        --board-soft: #fff1f2;
        --board-accent: #e11d48;
    }

    .board-stat--amber {
        --board-soft: #fffbeb;
        --board-accent: #d97706;
    }

    .board-bottom {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .board-panel {
        padding: 1.1rem;
    }

    .board-panel__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .board-panel__title {
        font-size: 1.05rem;
        font-weight: 760;
        letter-spacing: -.03em;
        color: #020617;
    }

    .board-panel__link {
        font-size: .8rem;
        font-weight: 700;
        color: #4f46e5;
    }

    .board-progress-list {
        gap: .8rem;
    }

    .board-progress-card {
        border-radius: 22px;
        padding: .95rem 1rem;
    }

    .board-progress__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .board-progress__value {
        font-size: .96rem;
        font-weight: 760;
        color: #020617;
    }

    .board-progress__track {
        margin-top: .75rem;
        width: 100%;
        height: .72rem;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .board-progress__fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--studio-accent), rgba(var(--studio-accent-rgb), .48));
    }

    .board-actions-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .board-action {
        display: grid;
        gap: .8rem;
        min-height: 128px;
        padding: 1rem;
        border-radius: 24px;
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }

    .board-action:hover {
        transform: translateY(-1px);
        border-color: rgba(var(--studio-accent-rgb), .3);
        box-shadow: 0 18px 28px -24px rgba(var(--studio-accent-rgb), .38);
    }

    .board-action__icon {
        width: 2.85rem;
        height: 2.85rem;
        border-radius: 20px;
        background: var(--studio-accent-soft);
        color: var(--studio-accent);
    }

    .board-action__title {
        font-size: .95rem;
        font-weight: 760;
        line-height: 1.25;
        color: #020617;
    }

    .board-action__meta {
        color: #64748b;
    }

    .board-action__value {
        margin-top: .3rem;
        font-size: 1.1rem;
        font-weight: 780;
        color: #020617;
    }

    .board-list {
        gap: .8rem;
    }

    .board-list__item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        padding: .9rem 1rem;
        border-radius: 22px;
    }

    .board-list__main {
        display: flex;
        align-items: center;
        gap: .8rem;
        min-width: 0;
    }

    .board-list__avatar {
        width: 2.65rem;
        height: 2.65rem;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(var(--studio-accent-rgb), .16), rgba(var(--studio-accent-rgb), .08));
        color: var(--studio-accent);
        font-size: .82rem;
        font-weight: 780;
    }

    .board-list__title {
        font-size: .92rem;
        font-weight: 730;
        color: #020617;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .board-list__sub {
        margin-top: .28rem;
        font-size: .82rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .board-empty {
        border-radius: 22px;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        padding: 1.3rem 1rem;
        text-align: center;
    }

    .board-empty__value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        color: #020617;
    }

    .board-empty__label {
        margin-top: .5rem;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: #64748b;
    }

    .board-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .42rem .72rem;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .board-status--warn {
        background: #fef3c7;
        color: #b45309;
    }

    .board-status--info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .board-status--success {
        background: #dcfce7;
        color: #15803d;
    }

    .board-status--danger {
        background: #ffe4e6;
        color: #be123c;
    }

    .stats-trend-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .stats-trend-card {
        --stats-trend-start: #6366f1;
        --stats-trend-end: #818cf8;
        --stats-trend-soft: #eef2ff;
        border-radius: 24px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fff, #f8fafc);
        padding: 1rem;
        box-shadow: 0 18px 34px -34px rgba(15, 23, 42, .38);
    }

    .stats-trend-card--indigo {
        --stats-trend-start: #6366f1;
        --stats-trend-end: #818cf8;
        --stats-trend-soft: #eef2ff;
    }

    .stats-trend-card--rose {
        --stats-trend-start: #f43f5e;
        --stats-trend-end: #fb7185;
        --stats-trend-soft: #fff1f2;
    }

    .stats-trend-card--emerald {
        --stats-trend-start: #10b981;
        --stats-trend-end: #34d399;
        --stats-trend-soft: #ecfdf5;
    }

    .stats-trend-card--amber {
        --stats-trend-start: #f59e0b;
        --stats-trend-end: #fbbf24;
        --stats-trend-soft: #fffbeb;
    }

    .stats-trend-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .stats-trend-label {
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: #64748b;
    }

    .stats-trend-total {
        margin-top: .35rem;
        font-size: 1.35rem;
        font-weight: 780;
        line-height: 1;
        color: #020617;
    }

    .stats-trend-meta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 3rem;
        border-radius: 999px;
        background: var(--stats-trend-soft);
        padding: .45rem .72rem;
        font-size: .78rem;
        font-weight: 800;
        color: #334155;
    }

    .stats-trend-bars {
        display: flex;
        align-items: flex-end;
        gap: .55rem;
        height: 9.25rem;
        margin-top: 1rem;
    }

    .stats-trend-col {
        flex: 1;
        min-width: 0;
    }

    .stats-trend-bar {
        width: 100%;
        min-height: .45rem;
        border-radius: 16px 16px 10px 10px;
        background: linear-gradient(180deg, var(--stats-trend-end), var(--stats-trend-start));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .24);
    }

    .stats-trend-value,
    .stats-trend-day {
        text-align: center;
        white-space: nowrap;
    }

    .stats-trend-value {
        margin-top: .45rem;
        font-size: .72rem;
        font-weight: 700;
        color: #334155;
    }

    .stats-trend-day {
        margin-top: .18rem;
        font-size: .68rem;
        color: #94a3b8;
    }

    .admin-navbar-mobile-balance {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: linear-gradient(180deg, #ecfdf5, #f7fff9);
        padding: .5rem .9rem;
        box-shadow: 0 12px 30px -26px rgba(15, 23, 42, .34);
        font-size: .85rem;
        font-weight: 700;
        color: #065f46;
        white-space: nowrap;
    }

    .admin-navbar-meta {
        display: none;
        align-items: center;
        justify-content: flex-end;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .admin-navbar-card {
        /* border-radius: 18px; */
        /* border: 1px solid #e2e8f0; */
        background: rgba(255, 255, 255, .92);
        padding: .55rem .85rem;
        box-shadow: 0 12px 30px -26px rgba(15, 23, 42, .34);
        text-align: left;
    }

    .admin-navbar-card--balance {
        border-color: #a7f3d0;
        background: linear-gradient(180deg, #ecfdf5, #f7fff9);
    }

    .admin-navbar-card__eyebrow {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .admin-navbar-card--balance .admin-navbar-card__eyebrow {
        color: #059669;
    }

    .admin-navbar-card__value {
        margin-top: .2rem;
        font-size: .9rem;
        font-weight: 650;
        color: #475569;
        white-space: nowrap;
    }

    .admin-navbar-card--balance .admin-navbar-card__value {
        color: #065f46;
    }

    .ai-studio-nav,
    .ai-studio-stack,
    .ai-studio-grid,
    .ai-studio-feed,
    .ai-studio-form-grid,
    .ai-studio-filter-grid,
    .ai-studio-persona-grid,
    .ai-studio-code-grid {
        display: grid;
        gap: 1rem;
    }

    .ai-studio-link {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid #dbe3ee;
        background: linear-gradient(180deg, #fff, #f8fafc);
        padding: 1rem 1.1rem;
        box-shadow: 0 18px 30px -30px rgba(15, 23, 42, .28);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
    }

    .ai-studio-link:hover {
        transform: translateY(-1px);
        border-color: rgba(var(--studio-accent-rgb), .3);
        box-shadow: 0 24px 36px -30px rgba(var(--studio-accent-rgb), .32);
    }

    .ai-studio-link--active {
        border-color: rgba(var(--studio-accent-rgb), .46);
        background: linear-gradient(180deg, rgba(var(--studio-accent-rgb), .12), rgba(255, 255, 255, .98));
        box-shadow: 0 28px 40px -30px rgba(var(--studio-accent-rgb), .45);
    }

    .ai-studio-link__title {
        margin-top: .45rem;
        font-size: 1rem;
        font-weight: 700;
        color: #020617;
    }

    .ai-studio-avatar {
        display: inline-flex;
        height: 2.85rem;
        width: 2.85rem;
        align-items: center;
        justify-content: center;
        border-radius: 22px;
        background: linear-gradient(135deg, rgba(var(--studio-accent-rgb), .9), rgba(var(--studio-accent-rgb), .58));
        font-size: .92rem;
        font-weight: 800;
        color: #fff;
        box-shadow: 0 18px 28px -20px rgba(var(--studio-accent-rgb), .52);
    }

    .ai-studio-inline-metric {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        border-radius: 999px;
        border: 1px solid #dbe3ee;
        background: #fff;
        padding: .55rem .9rem;
        font-size: .82rem;
        font-weight: 650;
        color: #334155;
    }

    .ai-studio-inline-metric__dot {
        width: .55rem;
        height: .55rem;
        border-radius: 999px;
        background: var(--studio-accent);
    }

    .ai-studio-list-card {
        border-radius: 28px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fff, #f8fafc);
        padding: 1rem 1.1rem;
        box-shadow: 0 20px 34px -34px rgba(15, 23, 42, .34);
    }

    .ai-studio-sticky-actions {
        position: sticky;
        bottom: 1rem;
        z-index: 15;
    }

    .ai-studio-empty {
        border-radius: 28px;
        border: 1px dashed #cbd5e1;
        background: linear-gradient(180deg, #fff, #f8fafc);
        padding: 1.5rem;
        text-align: center;
        font-size: .94rem;
        line-height: 1.7;
        color: #64748b;
    }

    @media (min-width:768px) {
        .ai-studio-nav {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ai-studio-filter-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .ai-studio-persona-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .admin-navbar-mobile-balance {
            display: none;
        }

        .admin-navbar-meta {
            display: flex;
        }

        .studio-card__header {
            flex-direction: row;
            align-items: flex-end;
            justify-content: space-between;
        }

        .studio-form-grid--2,
        .studio-choice-grid--2,
        .studio-toggle-grid--2,
        .studio-slider-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .studio-stat-grid--2,
        .studio-stat-grid--3,
        .studio-stat-grid--4,
        .studio-info-grid--2,
        .studio-info-grid--3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .studio-data-row {
            grid-template-columns: minmax(0, 1fr) minmax(0, auto);
            align-items: start;
        }

        .studio-toggle-grid--3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .studio-form-grid--3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .studio-form-grid--4 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .studio-actions {
            align-items: center;
        }

        .board-main__stats,
        .board-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .stats-trend-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .board-actions-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (min-width:1200px) {
        .studio-form-grid--4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .studio-stat-grid--3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .studio-stat-grid--4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .studio-info-grid--3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (min-width:1280px) {
        .ai-studio-nav {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .ai-studio-code-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .studio-grid--create {
            grid-template-columns: minmax(0, 1fr) 320px;
        }

        .studio-grid--detail {
            grid-template-columns: minmax(0, 1fr) 320px;
        }

        .studio-grid--edit {
            grid-template-columns: minmax(0, 1fr) 285px;
        }

        .studio-sidebar {
            position: sticky;
            top: 5.5rem;
        }

        .board-top {
            grid-template-columns: minmax(0, 1.45fr) 280px 280px;
            align-items: stretch;
        }

        .board-main__stats,
        .board-metrics {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .board-bottom {
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 1.2fr) minmax(0, .95fr);
        }

        .stats-trend-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .board-actions-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
