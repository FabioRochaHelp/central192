<style>
    /* Relatório final CB — estilos compatíveis com navegador, impressão e DomPDF */
    .rpt { color: #18181b; line-height: 1.5; font-size: 13px; }
    .rpt * { box-sizing: border-box; }

    .rpt-header {
        background: #18181b;
        color: #fafafa;
        padding: 22px 24px;
        margin: -2px -2px 20px;
        border-radius: 10px 10px 0 0;
        border-bottom: 4px solid #ea580c;
    }
    .rpt-header-title {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #ffffff;
    }
    .rpt-header-meta {
        margin: 6px 0 0;
        font-size: 13px;
        color: #a1a1aa;
    }
    .rpt-header-badge {
        display: inline-block;
        margin-top: 10px;
        padding: 4px 10px;
        background: #27272a;
        border: 1px solid #3f3f46;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        color: #fdba74;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .rpt-header-org {
        font-size: 12px;
        color: #d4d4d8;
        text-align: right;
    }
    .rpt-header-date {
        margin-top: 4px;
        font-size: 11px;
        color: #71717a;
        text-align: right;
    }

    .rpt-outcome {
        margin-bottom: 22px;
        padding: 16px 18px;
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        border: 1px solid #fed7aa;
        border-left: 5px solid #ea580c;
        border-radius: 8px;
    }
    .rpt-outcome-label {
        display: block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9a3412;
        margin-bottom: 4px;
    }
    .rpt-outcome-value {
        font-size: 20px;
        font-weight: 700;
        color: #7c2d12;
    }

    .rpt-section { margin-bottom: 22px; }
    .rpt-section-head {
        margin: 0 0 12px;
        padding: 0 0 8px 12px;
        border-left: 4px solid #ea580c;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #52525b;
    }

    .rpt-field-cell {
        padding: 0 8px 10px 0;
        vertical-align: top;
    }
    .rpt-field-box {
        background: #fafafa;
        border: 1px solid #e4e4e7;
        border-radius: 8px;
        padding: 10px 12px;
        min-height: 52px;
    }
    .rpt-field-label {
        display: block;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #71717a;
        margin-bottom: 4px;
    }
    .rpt-field-value {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #18181b;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .rpt-unit-card {
        border: 1px solid #e4e4e7;
        border-radius: 8px;
        margin-bottom: 10px;
        overflow: hidden;
    }
    .rpt-unit-head {
        background: #f4f4f5;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 700;
        color: #3f3f46;
        border-bottom: 1px solid #e4e4e7;
    }
    .rpt-unit-body { padding: 12px; }
    .rpt-unit-row { margin: 0 0 8px; font-size: 12px; }
    .rpt-unit-row:last-child { margin-bottom: 0; }
    .rpt-unit-row strong { color: #52525b; font-weight: 600; }
    .rpt-staff-list { margin: 6px 0 0; padding-left: 18px; }
    .rpt-staff-list li { margin-bottom: 3px; font-size: 12px; }

    .rpt-alert-box {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 12px 14px;
        margin-top: 12px;
    }
    .rpt-alert-box-title {
        margin: 0 0 8px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #92400e;
    }
    .rpt-alert-list { margin: 0; padding-left: 18px; }
    .rpt-alert-list li {
        margin-bottom: 6px;
        font-size: 12px;
        color: #451a03;
    }

    .rpt-description {
        background: #fafafa;
        border: 1px solid #e4e4e7;
        border-radius: 8px;
        padding: 14px 16px;
        white-space: pre-wrap;
        font-size: 13px;
        color: #3f3f46;
    }

    .rpt-footer {
        margin-top: 28px;
        padding-top: 14px;
        border-top: 1px solid #e4e4e7;
        text-align: center;
        font-size: 10px;
        color: #a1a1aa;
        letter-spacing: 0.02em;
    }

    .rpt-empty {
        color: #71717a;
        font-style: italic;
        font-size: 12px;
    }
</style>
