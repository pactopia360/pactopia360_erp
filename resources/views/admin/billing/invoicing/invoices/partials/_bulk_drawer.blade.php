<div class="invx-drawer" id="bulkDrawer" aria-hidden="true">
    <div class="invx-drawer__backdrop" data-invx-drawer-close></div>

    <aside class="invx-drawer__panel invx-drawer__panel--xl" role="dialog" aria-modal="true" aria-labelledby="bulkDrawerTitle">
        <div class="invx-drawer__head">
            <div>
                <h3 class="invx-drawer__title" id="bulkDrawerTitle">Carga masiva</h3>
                <p class="invx-drawer__sub">Registro compacto de varias facturas en una sola vista.</p>
            </div>
            <button type="button" class="invx-iconbtn" data-invx-drawer-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="invx-drawer__body">
            <form method="POST" action="{{ $routeStoreBulk }}" enctype="multipart/form-data">
                @csrf

                <div class="invx-bulk-wrap">
                    <table class="invx-bulk-table" id="invxBulkTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cuenta</th>
                                <th>Periodo</th>
                                <th>UUID</th>
                                <th>Serie</th>
                                <th>Folio</th>
                                <th>Estado</th>
                                <th>Monto</th>
                                <th>Fecha/hora</th>
                                <th>Fecha</th>
                                <th>Origen</th>
                                <th>Notas</th>
                                <th>PDF</th>
                                <th>XML</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < $bulkRowsCount; $i++)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><input type="text" name="account_id[]" class="invx-bulk-input" value="{{ $bulkOldAccountId[$i] ?? '' }}" placeholder="Cuenta"></td>
                                    <td><input type="text" name="period[]" class="invx-bulk-input" value="{{ $bulkOldPeriod[$i] ?? '' }}" placeholder="YYYY-MM"></td>
                                    <td><input type="text" name="cfdi_uuid[]" class="invx-bulk-input" value="{{ $bulkOldCfdiUuid[$i] ?? '' }}" placeholder="UUID"></td>
                                    <td><input type="text" name="serie[]" class="invx-bulk-input" value="{{ $bulkOldSerie[$i] ?? '' }}" placeholder="Serie"></td>
                                    <td><input type="text" name="folio[]" class="invx-bulk-input" value="{{ $bulkOldFolio[$i] ?? '' }}" placeholder="Folio"></td>
                                    <td><input type="text" name="status[]" class="invx-bulk-input" value="{{ $bulkOldStatus[$i] ?? 'issued' }}" placeholder="issued"></td>
                                    <td><input type="text" name="amount_mxn[]" class="invx-bulk-input" value="{{ $bulkOldAmountMxn[$i] ?? '' }}" placeholder="0.00"></td>
                                    <td><input type="text" name="issued_at[]" class="invx-bulk-input" value="{{ $bulkOldIssuedAt[$i] ?? '' }}" placeholder="2026-03-11 10:00:00"></td>
                                    <td><input type="text" name="issued_date[]" class="invx-bulk-input" value="{{ $bulkOldIssuedDate[$i] ?? '' }}" placeholder="2026-03-11"></td>
                                    <td><input type="text" name="source[]" class="invx-bulk-input" value="{{ $bulkOldSource[$i] ?? 'manual_bulk_admin' }}" placeholder="manual_bulk_admin"></td>
                                    <td><input type="text" name="notes[]" class="invx-bulk-input" value="{{ $bulkOldNotes[$i] ?? '' }}" placeholder="Notas"></td>
                                    <td><input type="file" name="pdf_files[{{ $i }}]" class="invx-bulk-input" accept="application/pdf"></td>
                                    <td><input type="file" name="xml_files[{{ $i }}]" class="invx-bulk-input" accept=".xml,application/xml,text/xml,.txt,text/plain"></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>

                <div class="invx-bulk-actions">
                    <button type="button" class="invx-btn invx-btn--soft invx-btn--sm" id="invxAddBulkRow">Agregar fila</button>
                </div>

                <div class="invx-form-grid invx-form-grid--mt">
                    <div class="invx-field invx-field--span-12">
                        <div class="invx-floating">
                            <input id="bulk_manual_to" type="text" name="to" class="invx-input" value="{{ old('to') }}" placeholder=" ">
                            <label for="bulk_manual_to">Correos destino para envío inmediato</label>
                        </div>
                    </div>

                    <div class="invx-field invx-field--span-12">
                        <label class="invx-checkline">
                            <input type="checkbox" name="send_now" value="1" class="invx-check" {{ old('send_now') ? 'checked' : '' }}>
                            <span>Intentar envío por correo para cada factura guardada</span>
                        </label>
                    </div>

                    <div class="invx-field invx-field--span-12">
                        <div class="invx-form-actions">
                            <button type="button" class="invx-btn invx-btn--soft" data-invx-drawer-close>Cerrar</button>
                            <button type="submit" class="invx-btn invx-btn--primary">Registrar lote</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </aside>
</div>