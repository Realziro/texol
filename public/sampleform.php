<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Feedback Form - TEX-MAC-FRM-005</title>
<style>
  :root{
    --brand-red:#FF0000;
    --line-color:#000;
    --font-main:'Century Gothic','Apple Gothic','Avenir Next','Segoe UI',sans-serif;
  }

  *{ box-sizing:border-box; }

  body{
    font-family:var(--font-main);
    color:#000;
    font-size:11pt;
    background:#e9e9e9;
    margin:0;
    padding:24px 0;
  }

  .page{
    width:8.5in;
    min-height:11in;
    margin:0 auto 24px;
    background:#fff;
    padding:0.6in 0.75in 0.9in;
    box-shadow:0 0 8px rgba(0,0,0,0.25);
    position:relative;
  }

  /* ===== HEADER TABLE ===== */
  table.doc-header{
    width:100%;
    border-collapse:collapse;
    margin-bottom:22px;
    table-layout:fixed;
  }
  table.doc-header td{
    border:1px solid #000;
    padding:4px 8px;
    vertical-align:middle;
    font-size:10pt;
  }
  table.doc-header .company-row td{
    text-align:center;
    font-weight:bold;
    font-size:10pt;
    padding:6px 8px;
  }
  table.doc-header .logo-cell{
    width:20%;
    text-align:center;
  }
  table.doc-header .logo-cell img{
    max-width:95px;
    height:auto;
    display:block;
    margin:0 auto;
  }
  table.doc-header .title-cell{
    width:50%;
    text-align:center;
    color:var(--brand-red);
    font-weight:bold;
    font-size:10pt;
  }
  table.doc-header .meta-cell{
    width:30%;
    text-align:center;
    font-weight:bold;
    font-size:9.5pt;
    line-height:1.5;
  }

  /* ===== BODY ===== */
  h1.form-title{
    text-align:center;
    font-weight:bold;
    font-size:16pt;
    margin:0 0 18px;
  }

  h2.section-heading{
    font-weight:bold;
    font-size:11pt;
    margin:16px 0 2px;
  }

  p.section-note{
    font-style:italic;
    margin:0 0 6px;
    font-size:11pt;
  }

  ul.field-list{
    list-style:none;
    margin:0 0 4px;
    padding:0;
  }

  ul.field-list li.field-row{
    display:flex;
    align-items:flex-end;
    margin:6px 0;
  }

  ul.field-list li.field-row::before{
    content:"\2022";
    margin-right:8px;
    flex:0 0 auto;
    font-weight:bold;
  }

  .field-label{
    font-weight:bold;
    white-space:nowrap;
    margin-right:6px;
    flex:0 0 auto;
  }

  .field-blank{
    flex:1 1 auto;
    border-bottom:1px dotted #000;
    height:1em;
  }

  .blank-line{
    border-bottom:1px dotted #000;
    height:1em;
    margin:10px 0 10px 22px;
  }

  /* ===== FOOTER ===== */
  .page-footer{
    position:absolute;
    bottom:0.4in;
    left:0.75in;
    right:0.75in;
    font-style:italic;
    font-size:9pt;
    border-top:1px solid #000;
    padding-top:4px;
  }

  @media print{
    body{ background:#fff; padding:0; }
    .page{
      box-shadow:none;
      margin:0;
      width:auto;
      min-height:auto;
      padding:0.6in 0.75in 0.9in;
    }
  }
</style>
</head>
<body>

<div class="page">

  <!-- ================= HEADER ================= -->
  <table class="doc-header">
    <tr class="company-row">
      <td colspan="3">TEXOL ENERGIES LIMITED</td>
    </tr>
    <tr>
      <td class="logo-cell" rowspan="2">
        <img src="logo.png" alt="Texol Energies Logo">
      </td>
      <td class="title-cell" rowspan="2">Customer Feedback Form</td>
      <td class="meta-cell">
        TEX-MAC-FRM-005, Ver 000<br>
        Issue Date: 1<sup>st</sup> Nov 2024
      </td>
    </tr>
    <tr>
      <td class="meta-cell">Page 1 of 1</td>
    </tr>
  </table>

  <!-- ================= BODY ================= -->
  <h1 class="form-title">Customer Feedback Form</h1>

  <h2 class="section-heading">Client&rsquo;s Information</h2>
  <ul class="field-list">
    <li class="field-row"><span class="field-label">Client's Name:</span><span class="field-blank"></span></li>
    <li class="field-row"><span class="field-label">Card Number:</span><span class="field-blank"></span></li>
    <li class="field-row"><span class="field-label">Station:</span><span class="field-blank"></span></li>
  </ul>

  <h2 class="section-heading">Complaint or Feedback Details</h2>
  <p class="section-note">(Please describe the issue or feedback in detail)</p>
  <ul class="field-list">
    <li class="field-row"><span class="field-label">Complaint Details:</span><span class="field-blank"></span></li>
  </ul>
  <div class="blank-line"></div>
  <div class="blank-line"></div>

  <h2 class="section-heading">Incident Information</h2>
  <ul class="field-list">
    <li class="field-row"><span class="field-label">Date of Incident:</span><span class="field-blank"></span></li>
    <li class="field-row"><span class="field-label">Time of Incident:</span><span class="field-blank"></span></li>
    <li class="field-row"><span class="field-label">Attendant Name/ID (if applicable):</span><span class="field-blank"></span></li>
  </ul>

  <h2 class="section-heading">Call Center Interaction Information</h2>
  <ul class="field-list">
    <li class="field-row"><span class="field-label">Date of Call:</span><span class="field-blank"></span></li>
    <li class="field-row"><span class="field-label">Time of Call:</span><span class="field-blank"></span></li>
    <li class="field-row"><span class="field-label">Agent's Name:</span><span class="field-blank"></span></li>
    <li class="field-row"><span class="field-label">Agent's Signature:</span><span class="field-blank"></span></li>
  </ul>

  <h2 class="section-heading">Resolution (For Internal Use Only)</h2>
  <p class="section-note">(Please describe how the complaint or feedback was resolved or any corrective/preventive actions taken for non-conformance resolution)</p>
  <ul class="field-list">
    <li class="field-row"><span class="field-label">Resolution Provided:</span><span class="field-blank"></span></li>
  </ul>
  <div class="blank-line"></div>

  <h2 class="section-heading">Additional Comments (Optional)</h2>
  <p class="section-note">(For any further details or suggestions that may contribute to the continual improvement of services)</p>
  <ul class="field-list">
    <li class="field-row"><span class="field-label">Additional Comments:</span><span class="field-blank"></span></li>
  </ul>

  <!-- ================= FOOTER ================= -->
  <div class="page-footer">Texol Energies Ltd Customer Feedback Form</div>

</div>

</body>
</html>
