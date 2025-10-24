{{-- resources/views/layouts/partials/client_footer.blade.php --}}
<footer role="contentinfo" class="client-footer" style="margin-top:16px">
  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <small>© {{ date('Y') }} Pactopia360</small>
    <small style="margin-left:auto">
      <a href="{{ url('/cliente/terminos') }}" style="color:inherit">Términos</a>
    </small>
    <small>
      <a href="{{ url('/cliente/soporte') }}" style="color:inherit">Soporte</a>
    </small>
  </div>
</footer>
