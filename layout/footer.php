<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap');
  .portal-footer {
    font-family: 'DM Sans', sans-serif;
    background: linear-gradient(90deg, #1A1A2E 0%, #16213E 100%);
    border-top: 1px solid rgba(184,150,62,0.25);
    padding: 0.75rem 1.5rem;
    position: relative;
  }
  /* Thin gold top accent */
  .portal-footer::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 2px;
    background: linear-gradient(to right, transparent, #B8963E, transparent);
    opacity: 0.5;
  }
  .portal-footer .footer-inner {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
  }
  .portal-footer .footer-dev {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.4);
    letter-spacing: 0.04em;
  }
  .portal-footer .footer-dev strong {
    color: #D4AF68;
    font-weight: 600;
  }
  .portal-footer .footer-school {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.4);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 0.4rem;
  }
  .portal-footer .footer-school i {
    color: #B8963E;
    font-size: 0.7rem;
  }
  .portal-footer .footer-copy {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.25);
    letter-spacing: 0.04em;
  }
  @media (max-width: 576px) {
    .portal-footer .footer-inner {
      flex-direction: column;
      text-align: center;
      gap: 0.3rem;
    }
  }
</style>
<footer class="portal-footer">
  <div class="footer-inner container-fluid px-0">
    <span class="footer-dev">
      Developed by: <strong>Jona Mae &amp; Chenmae</strong> &mdash; BSIT 3C
    </span>
    <span class="footer-school">
      <i class="bi bi-building"></i>
      Northeastern Cebu Colleges, Inc.
    </span>
    <span class="footer-copy">
      &copy; 2026 Admission Portal
    </span>
  </div>
</footer>