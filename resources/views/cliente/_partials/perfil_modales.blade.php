{{-- resources/views/cliente/_partials/perfil_modales.blade.php --}}
{{-- Modales estándar para la pantalla de perfil de cliente --}}

@php
  $theme = session('client_ui.theme','light');
@endphp

{{-- ===========================
     MODAL: Cambiar contraseña
   =========================== --}}
<div class="p360-modal" id="perfil-modal-password" data-modal>
  <div class="p360-modal-backdrop" data-modal-backdrop></div>

  <div class="p360-modal-dialog">
    <div class="p360-modal-header">
      <h3>Cambiar contraseña</h3>
      <button type="button" class="p360-modal-close" data-modal-close>
        &times;
      </button>
    </div>

    <form method="POST" action="{{ route('cliente.perfil.password.update') }}">
      @csrf
      @method('PUT')

      <div class="p360-modal-body">
        <div class="p360-field">
          <label for="current_password">Contraseña actual</label>
          <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        </div>

        <div class="p360-field">
          <label for="password">Nueva contraseña</label>
          <input id="password" name="password" type="password" required autocomplete="new-password">
        </div>

        <div class="p360-field">
          <label for="password_confirmation">Confirmar nueva contraseña</label>
          <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
      </div>

      <div class="p360-modal-footer">
        <button type="button" class="btn-secondary" data-modal-close>Cancelar</button>
        <button type="submit" class="btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

{{-- ===========================
     MODAL: Actualizar teléfono
   =========================== --}}
<div class="p360-modal" id="perfil-modal-phone" data-modal>
  <div class="p360-modal-backdrop" data-modal-backdrop></div>

  <div class="p360-modal-dialog">
    <div class="p360-modal-header">
      <h3>Actualizar teléfono</h3>
      <button type="button" class="p360-modal-close" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="{{ route('cliente.perfil.phone.update') }}">
      @csrf
      @method('PUT')

      <div class="p360-modal-body">
        <div class="p360-field">
          <label for="phone">Número de teléfono</label>
          <input id="phone" name="phone" type="tel" required maxlength="20"
                 value="{{ old('phone', auth('web')->user()->telefono ?? '') }}">
          <small>Usaremos este número para verificaciones y notificaciones.</small>
        </div>
      </div>

      <div class="p360-modal-footer">
        <button type="button" class="btn-secondary" data-modal-close>Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

{{-- ===========================
     ESTILOS BÁSICOS (scope)
   =========================== --}}
@push('styles')
<style>
  .p360-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:60;}
  .p360-modal.is-open{display:flex;}
  .p360-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45);}
  .p360-modal-dialog{
    position:relative;z-index:1;width:100%;max-width:480px;
    border-radius:18px;padding:20px 22px;background:#fff;
    box-shadow:0 24px 60px rgba(15,23,42,.35);
  }
  [data-theme="dark"] .p360-modal-dialog{background:#020617;color:#e5e7eb;}
  .p360-modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
  .p360-modal-header h3{font-size:1.05rem;font-weight:600;margin:0;}
  .p360-modal-close{
    border:none;background:transparent;font-size:1.4rem;line-height:1;
    cursor:pointer;padding:2px 6px;border-radius:999px;
  }
  .p360-modal-body{display:grid;gap:12px;margin-top:4px;margin-bottom:14px;}
  .p360-field label{display:block;font-size:.8rem;font-weight:500;margin-bottom:4px;}
  .p360-field input{
    width:100%;border-radius:10px;border:1px solid #e5e7eb;
    padding:8px 10px;font-size:.9rem;
  }
  .p360-field small{display:block;font-size:.7rem;color:#6b7280;margin-top:4px;}
  .p360-modal-footer{display:flex;justify-content:flex-end;gap:8px;}
  .btn-primary,.btn-secondary{
    border-radius:999px;border:1px solid transparent;
    padding:6px 14px;font-size:.85rem;font-weight:500;cursor:pointer;
  }
  .btn-primary{background:#E11D48;color:#fff;border-color:#BE123C;}
  .btn-secondary{background:#f8fafc;color:#0f172a;border-color:#e5e7eb;}
  [data-theme="dark"] .btn-secondary{background:#020617;color:#e5e7eb;border-color:#1e293b;}
</style>
@endpush

{{-- ===========================
     SCRIPT BÁSICO
   =========================== --}}
@push('scripts')
<script>
  (function(){
    function openModal(id){
      var m=document.getElementById(id);
      if(!m) return;
      m.classList.add('is-open');
    }
    function closeModal(el){
      var m=el.closest('.p360-modal');
      if(m) m.classList.remove('is-open');
    }

    document.addEventListener('click',function(e){
      var openTarget=e.target.closest('[data-open-modal]');
      if(openTarget){
        e.preventDefault();
        var id=openTarget.getAttribute('data-open-modal');
        if(id) openModal(id);
        return;
      }

      if(e.target.matches('[data-modal-backdrop],[data-modal-close]')){
        e.preventDefault();
        closeModal(e.target);
      }
    });
  })();
</script>
@endpush
