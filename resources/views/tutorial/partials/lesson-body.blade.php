@php
    $showLinks = $showLinks ?? true;
    $links = $links ?? [];
@endphp

@if ($lessonKey === 'bienvenida')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Cómo usar este tutorial</h4>
                <p>Avanza con <strong>Siguiente</strong>. También puedes saltar a una lección tocando su número arriba.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Qué aprenderás</h4>
                <p>Configurar la empresa, gestionar clientes, vender, producir y revisar reportes.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Consejo</h4>
                <p>Si es la primera vez, completa las lecciones en orden. Si ya conoces el sistema, ve directo a la lección que necesitas.</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'acceso')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Inicia sesión</h4>
                <p>Entra con tu correo y contraseña en la pantalla de acceso del panel.</p>
                <p class="saas-guide-where">Dónde: pantalla de login → <code>/admin/login</code></p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Actualiza tu perfil</h4>
                <p>Desde el menú del avatar (abajo a la izquierda) puedes cambiar foto, nombre, correo, contraseña y PIN de operario.</p>
                <p class="saas-guide-where">Dónde: avatar → Mi perfil</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Roles habituales</h4>
                <p><strong>Administrador:</strong> configura todo. <strong>Vendedor / Gerencia:</strong> CRM, ventas y alertas cuando planta termina. <strong>Operario:</strong> ve un Inicio de planta (pendientes y productividad) y usa el Escaneo.</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'arranque')
    <p class="saas-guide-intro">Si vas a llenar Korapp por primera vez, sigue este orden:</p>
    <ol class="saas-guide-roadmap">
        <li><strong>Empresa y usuarios</strong><span>Logo, colores y cuentas del equipo</span></li>
        <li><strong>Clientes</strong><span>Alta manual o importación CSV</span></li>
        <li><strong>Procesos</strong><span>Etapas de producción (corte, ensamble…)</span></li>
        <li><strong>Operación diaria</strong><span>Cotización → OP → Venta → Entrega</span></li>
    </ol>
@endif

@if ($lessonKey === 'empresa')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Identidad de la empresa</h4>
                <p>Define nombre, eslogan y logo (PNG o SVG). Si subes logo, ese será el brand del menú.</p>
                <p class="saas-guide-where">Dónde: General → Empresa</p>
                @if ($showLinks && ! empty($links['empresa']))
                    <a href="{{ $links['empresa'] }}" class="saas-guide-link">Abrir Empresa</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Datos de contacto</h4>
                <p>Correo, teléfono, NIT, web y dirección. Se usan en documentos y reportes.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>IVA</h4>
                <p>Indica si la empresa cobra IVA y el porcentaje. Se aplica en ventas, cotizaciones y punto de venta.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Apariencia</h4>
                <p>Elige el tema del menú (Claro, Índigo o Personalizado) y el color de botones.</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'seguridad')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Crea usuarios</h4>
                <p>Nombre, correo, rol y PIN opcional. La contraseña inicial es la predeterminada del sistema (<strong>password</strong>); al entrar el usuario debe cambiarla obligatoriamente.</p>
                <p class="saas-guide-where">Dónde: Accesos → Usuarios</p>
                @if ($showLinks && ! empty($links['usuarios']))
                    <a href="{{ $links['usuarios'] }}" class="saas-guide-link">Abrir Usuarios</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Ajusta roles</h4>
                <p>Cada rol define qué pantallas y acciones puede usar una persona.</p>
                <p class="saas-guide-where">Dónde: Accesos → Roles</p>
                @if ($showLinks && ! empty($links['roles']))
                    <a href="{{ $links['roles'] }}" class="saas-guide-link">Abrir Roles</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'inventario')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Bodegas</h4>
                <p>Crea al menos una bodega y márcala como predeterminada.</p>
                <p class="saas-guide-where">Dónde: Inventario → Bodegas</p>
                @if ($showLinks && ! empty($links['bodegas']))
                    <a href="{{ $links['bodegas'] }}" class="saas-guide-link">Abrir Bodegas</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Categorías y artículos</h4>
                <p>Organiza el catálogo y crea artículos con SKU, precios, stock mínimo y existencias.</p>
                <p class="saas-guide-where">Dónde: Inventario → Categorías / Artículos</p>
                @if ($showLinks)
                    <div class="saas-guide-actions">
                        @if (! empty($links['categorias']))
                            <a href="{{ $links['categorias'] }}" class="saas-guide-link">Categorías</a>
                        @endif
                        @if (! empty($links['articulos']))
                            <a href="{{ $links['articulos'] }}" class="saas-guide-link saas-guide-link--ghost">Artículos</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Movimientos (kardex)</h4>
                <p>Consulta entradas y salidas. Se generan al comprar, vender, producir o ajustar.</p>
                <p class="saas-guide-where">Dónde: Inventario → Movimientos</p>
                @if ($showLinks && ! empty($links['movimientos']))
                    <a href="{{ $links['movimientos'] }}" class="saas-guide-link">Abrir Movimientos</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Proveedores</h4>
                <p>Regístralos antes de crear compras.</p>
                <p class="saas-guide-where">Dónde: Inventario → Proveedores</p>
                @if ($showLinks && ! empty($links['proveedores']))
                    <a href="{{ $links['proveedores'] }}" class="saas-guide-link">Abrir Proveedores</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'clientes')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Crear o importar</h4>
                <p>Alta manual, o descarga el formato, llénalo en Excel, guárdalo como <strong>CSV UTF-8</strong> e impórtalo.</p>
                <p class="saas-guide-where">Dónde: Ventas → Clientes</p>
                @if ($showLinks)
                    <div class="saas-guide-actions">
                        @if (! empty($links['plantilla_clientes']))
                            <a href="{{ $links['plantilla_clientes'] }}" class="saas-guide-link">Descargar formato</a>
                        @endif
                        @if (! empty($links['clientes']))
                            <a href="{{ $links['clientes'] }}" class="saas-guide-link saas-guide-link--ghost">Ir a Clientes</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>NIT / documento y mayúsculas</h4>
                <p>El documento es necesario para pedidos formales. Los textos del cliente (nombre, ciudad, etc.) se guardan en <strong>MAYÚSCULAS</strong>; el correo no.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Reasignar cartera</h4>
                <p>Pasa clientes (y opcionalmente prospectos) de un vendedor a otro.</p>
                <p class="saas-guide-where">Dónde: Ventas → Reasignar cartera</p>
                @if ($showLinks && ! empty($links['reasignar']))
                    <a href="{{ $links['reasignar'] }}" class="saas-guide-link">Abrir Reasignar</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'ventas')
    <div class="saas-guide-callout" style="margin-bottom:1rem;padding:.85rem 1rem;border:1px solid var(--saas-border,#e5e7eb);border-radius:.75rem;background:#f8fafc;">
        <p style="margin:0;font-weight:650;">Orden del menú Ventas</p>
        <p style="margin:.35rem 0 0;color:#475569;">Panel → Prospectos → Embudo → Agenda → <strong>Cotizador</strong> → Cotizaciones → Ventas</p>
    </div>

    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Panel y prospectos</h4>
                <p>Revisa el panel comercial y trabaja prospectos en pestañas: Activos, Convertidos, Perdidos.</p>
                <p class="saas-guide-where">Dónde: Ventas → Panel de ventas / Prospectos</p>
                @if ($showLinks)
                    <div class="saas-guide-actions">
                        @if (! empty($links['panel_ventas']))
                            <a href="{{ $links['panel_ventas'] }}" class="saas-guide-link">Panel</a>
                        @endif
                        @if (! empty($links['prospectos']))
                            <a href="{{ $links['prospectos'] }}" class="saas-guide-link saas-guide-link--ghost">Prospectos</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Embudo y agenda</h4>
                <p>En el embudo arrastra etapas; en Propuesta/Negociación usa <strong>Ganar</strong> o <strong>Perder</strong>. En Agenda programa visitas o llamadas.</p>
                <p class="saas-guide-where">Dónde: Ventas → Embudo de ventas · Agenda comercial</p>
                @if ($showLinks)
                    <div class="saas-guide-actions">
                        @if (! empty($links['embudo']))
                            <a href="{{ $links['embudo'] }}" class="saas-guide-link">Embudo</a>
                        @endif
                        @if (! empty($links['agenda']))
                            <a href="{{ $links['agenda'] }}" class="saas-guide-link saas-guide-link--ghost">Agenda</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Cotizador</h4>
                <p>Arma la cotización comercial por piezas. Guarda y queda en <strong>Cotizaciones</strong>.</p>
                <p class="saas-guide-where">Dónde: Ventas → Cotizador</p>
                @if ($showLinks && ! empty($links['cotizador']))
                    <a href="{{ $links['cotizador'] }}" class="saas-guide-link">Abrir Cotizador</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Cotizaciones</h4>
                <p>Revisa el PDF, marca como enviada y, cuando el negocio cierre, usa <strong>Validar y aceptar</strong> (no solo cambiar el estado a mano).</p>
                <p class="saas-guide-where">Dónde: Ventas → Cotizaciones</p>
                @if ($showLinks && ! empty($links['cotizaciones']))
                    <a href="{{ $links['cotizaciones'] }}" class="saas-guide-link">Abrir Cotizaciones</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>5</span>
            <div>
                <h4>Siguiente: pedido completo</h4>
                <p>El flujo del trabajo es <strong>Cotización → OP → Venta → Entrega</strong>. Detalle en la lección Pedido completo. En la cotización verás el tablero de esos 4 pasos.</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'pedido')
    <div class="saas-guide-callout" style="margin-bottom:1rem;padding:.85rem 1rem;border:1px solid var(--saas-border,#e5e7eb);border-radius:.75rem;background:#f8fafc;">
        <p style="margin:0;font-weight:650;">Flujo oficial del trabajo</p>
        <p style="margin:.35rem 0 0;color:#475569;"><strong>1.</strong> Cotización → <strong>2.</strong> Orden de producción → <strong>3.</strong> Venta → <strong>4.</strong> Entrega</p>
    </div>

    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Validar y aceptar cotización</h4>
                <p>Completa cliente/prospecto, líneas y total. Usa <strong>Validar y aceptar</strong>: el sistema revisa requisitos. Al aceptar, la cotización queda en solo lectura y aparece el tablero de 4 pasos.</p>
                <p class="saas-guide-where">Dónde: Ventas → Cotizador / Cotizaciones → Validar y aceptar</p>
                @if ($showLinks)
                    <div class="saas-guide-actions">
                        @if (! empty($links['cotizador']))
                            <a href="{{ $links['cotizador'] }}" class="saas-guide-link">Cotizador</a>
                        @endif
                        @if (! empty($links['cotizaciones']))
                            <a href="{{ $links['cotizaciones'] }}" class="saas-guide-link saas-guide-link--ghost">Cotizaciones</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Crear orden de producción</h4>
                <p>En la cotización aceptada usa <strong>Crear OP</strong> y marca los procesos de planta. Imprime la OP o las etiquetas QR.</p>
                <p class="saas-guide-where">Dónde: Cotizaciones → Crear OP · Producción → Órdenes de producción</p>
                @if ($showLinks)
                    <div class="saas-guide-actions">
                        @if (! empty($links['ordenes']))
                            <a href="{{ $links['ordenes'] }}" class="saas-guide-link">Órdenes</a>
                        @endif
                        @if (! empty($links['escaneo']))
                            <a href="{{ $links['escaneo'] }}" class="saas-guide-link saas-guide-link--ghost">Escaneo</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Fabricar en planta</h4>
                <p>El operario escanea cada etapa (inicio / fin). Cuando todas terminan, la OP queda <strong>Completada</strong>. Sin eso no se puede crear la venta.</p>
                <p class="saas-guide-where">Dónde: Producción → Escaneo (operario)</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Crear venta / factura</h4>
                <p>En la cotización → <strong>Crear venta</strong>. Puedes registrar <strong>anticipo</strong> y ver el saldo. Si solo había prospecto, aquí se crea el Cliente. Luego <strong>Confirmar</strong> la venta.</p>
                <p class="saas-guide-where">Dónde: Cotizaciones → Crear venta · Ventas → Confirmar</p>
                @if ($showLinks && ! empty($links['ventas']))
                    <a href="{{ $links['ventas'] }}" class="saas-guide-link">Abrir Ventas</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>5</span>
            <div>
                <h4>Entrega final</h4>
                <p>Con la OP <strong>Completada</strong> y la venta <strong>confirmada</strong>, registra la entrega. Si es stock (lámina / sin fabricar), no hay OP: la factura confirmada ya aparece en Entregas. Opcional: anota quién recibió. También existe <strong>Entrega final</strong> por OP.</p>
                <p class="saas-guide-where">Dónde: Comercial → Entregas · Producción → Órdenes → Entrega final</p>
                @if ($showLinks && ! empty($links['entregas']))
                    <a href="{{ $links['entregas'] }}" class="saas-guide-link">Abrir Entregas</a>
                @endif
            </div>
        </div>
    </div>

    <div class="saas-guide-callout" style="margin-top:1rem;padding:.85rem 1rem;border:1px solid var(--saas-border,#e5e7eb);border-radius:.75rem;background:#fff7ed;">
        <p style="margin:0;font-weight:650;">Si es un prospecto</p>
        <ul style="margin:.5rem 0 0;padding-left:1.1rem;color:#475569;">
            <li>Cotizas y fabricas con el prospecto (aún sin cliente).</li>
            <li>Al crear la venta, el prospecto se convierte en cliente (usa la empresa si está diligenciada).</li>
            <li>Si ya tenía “Cliente vinculado”, se reutiliza ese cliente.</li>
        </ul>
    </div>
@endif

@if ($lessonKey === 'entregas')
    <div class="saas-guide-callout" style="margin-bottom:1rem;padding:.85rem 1rem;border:1px solid var(--saas-border,#e5e7eb);border-radius:.75rem;background:#f0fdf4;">
        <p style="margin:0;font-weight:650;">Cuándo aparece una factura aquí</p>
        <p style="margin:.35rem 0 0;color:#475569;"><strong>Con OP:</strong> OP Completada + venta confirmada. <strong>Sin OP (stock / lámina):</strong> venta confirmada basta; no pasa por producción. El estado de la venta no cambia a “entregada”: solo se registra la fecha de entrega.</p>
    </div>

    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Revisa la cola</h4>
                <p>En <strong>Comercial → Entregas</strong> ves <strong>facturas</strong> en tres pestañas: <strong>Listas para entregar</strong>, <strong>Atrasadas</strong> (solo con OP y fecha pactada vencida) y <strong>Entregadas</strong>. El progreso muestra OPs o <strong>Stock / sin OP</strong>. En Inicio también hay un resumen.</p>
                <p class="saas-guide-where">Dónde: Comercial → Entregas · Principal → Inicio</p>
                @if ($showLinks && ! empty($links['entregas']))
                    <a href="{{ $links['entregas'] }}" class="saas-guide-link">Abrir Entregas</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Registra la entrega</h4>
                <p>Pulsa <strong>Entregar</strong> en la factura. Puedes indicar <strong>Recibido por</strong>. Con OP: las OPs listas pasan a Entregado. Sin OP: se guarda la fecha de entrega en la venta. Avisa la campana una sola vez.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Desde Producción</h4>
                <p>Gerencia/admin también pueden usar <strong>Entrega final</strong> en cada OP (útil si solo entregas una parte). Las pestañas Listas / Atrasadas / Entregadas siguen en Órdenes.</p>
                <p class="saas-guide-where">Dónde: Producción → Órdenes de producción</p>
                @if ($showLinks && ! empty($links['ordenes']))
                    <a href="{{ $links['ordenes'] }}" class="saas-guide-link">Abrir Órdenes</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'produccion')
    <div class="saas-guide-callout" style="margin-bottom:1rem;padding:.85rem 1rem;border:1px solid var(--saas-border,#e5e7eb);border-radius:.75rem;background:#f8fafc;">
        <p style="margin:0;font-weight:650;">Menú Producción</p>
        <p style="margin:.35rem 0 0;color:#475569;">Órdenes de producción → Escaneo (operario) → Procesos</p>
    </div>

    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Define procesos</h4>
                <p>Etapas por departamento (Impresión, Láser, Miscelánea, Control calidad…). Cada una puede tener QR.</p>
                <p class="saas-guide-where">Dónde: Producción → Procesos</p>
                @if ($showLinks && ! empty($links['procesos']))
                    <a href="{{ $links['procesos'] }}" class="saas-guide-link">Abrir Procesos</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Orden desde cotización</h4>
                <p>La OP se crea solo desde una cotización <strong>validada y aceptada</strong> (<strong>Crear OP</strong>). Luego imprime <strong>Imprimir OP</strong>, <strong>QR / Barras</strong> o Etiquetas.</p>
                <p class="saas-guide-where">Dónde: Cotizaciones · Producción → Órdenes de producción</p>
                @if ($showLinks && ! empty($links['ordenes']))
                    <a href="{{ $links['ordenes'] }}" class="saas-guide-link">Abrir Órdenes</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Escaneo e Inicio del operario</h4>
                <p>El operario ve en <strong>Inicio</strong> pendientes, en proceso y productividad. Escanea el QR: primer escaneo inicia, segundo finaliza. Puede usar PIN.</p>
                <p class="saas-guide-where">Dónde: General → Inicio (rol Operario) · Producción → Escaneo</p>
                @if ($showLinks && ! empty($links['escaneo']))
                    <a href="{{ $links['escaneo'] }}" class="saas-guide-link">Abrir Escaneo</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Aviso a ventas / gerencia</h4>
                <p>Cuando la OP queda <strong>Completada</strong>, aparece una alerta en la <strong>campana</strong> del panel (ventas, gerencia y administradores). Desde ahí puedes abrir la OP y crear la venta.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>5</span>
            <div>
                <h4>Entrega final</h4>
                <p>Después de <strong>Completada</strong> en planta y de <strong>confirmar la venta</strong>, usa <strong>Comercial → Entregas</strong> (por factura) o <strong>Entrega final</strong> en la OP. No entregues con venta en borrador.</p>
                <p class="saas-guide-where">Dónde: Comercial → Entregas · Producción → Órdenes</p>
                @if ($showLinks && ! empty($links['entregas']))
                    <a href="{{ $links['entregas'] }}" class="saas-guide-link">Abrir Entregas</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'compras')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Elige proveedor</h4>
                <p>Debe existir en Inventario → Proveedores.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Registra y recibe</h4>
                <p>Crea la compra con líneas y usa <strong>Recibir</strong> para entrar mercancía al stock.</p>
                <p class="saas-guide-where">Dónde: Compras → Compras</p>
                @if ($showLinks && ! empty($links['compras']))
                    <a href="{{ $links['compras'] }}" class="saas-guide-link">Abrir Compras</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'reportes')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Reportes</h4>
                <p>Ventas por periodo, top productos y rotación. Exporta CSV si lo necesitas.</p>
                <p class="saas-guide-where">Dónde: Reportes → Reportes</p>
                @if ($showLinks && ! empty($links['reportes']))
                    <a href="{{ $links['reportes'] }}" class="saas-guide-link">Abrir Reportes</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Actividad</h4>
                <p>Consulta quién hizo qué cambio. Solo lectura.</p>
                <p class="saas-guide-where">Dónde: Actividad</p>
                @if ($showLinks && ! empty($links['auditoria']))
                    <a href="{{ $links['auditoria'] }}" class="saas-guide-link">Abrir Actividad</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Inicio</h4>
                <p>El tablero general muestra KPIs. Filtra por 7, 30 o 90 días.</p>
                <p class="saas-guide-where">Dónde: General → Inicio</p>
                @if ($showLinks && ! empty($links['dashboard']))
                    <a href="{{ $links['dashboard'] }}" class="saas-guide-link">Abrir Inicio</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'flujos')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>A</span>
            <div>
                <h4>Prospecto → Pedido</h4>
                <p>Prospecto → Cotizador/Cotización → <strong>Validar y aceptar</strong> → Crear OP → Fabricar (Completada) → Crear venta (+ anticipo si aplica) → Confirmar → <strong>Entregas</strong>.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>B</span>
            <div>
                <h4>Cliente → Pedido</h4>
                <p>Cliente → Cotizador/Cotización → Validar y aceptar → Crear OP → Fabricar → Crear venta → Confirmar → <strong>Entregas</strong>.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>C</span>
            <div>
                <h4>Día del operario</h4>
                <p>Inicio (pendientes y productividad) → Escaneo de etapas → cuando la OP está Completada, el vendedor/admin hace venta y entrega.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>D</span>
            <div>
                <h4>Cola de entregas</h4>
                <p>Inicio muestra facturas pendientes. En <strong>Comercial → Entregas</strong> entregas por factura (todas las OPs listas de esa venta) con “recibido por” opcional; avisa la campana.</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'faq')
    <ul class="saas-guide-faq">
        <li><strong>¿Cuál es el orden del trabajo?</strong> Cotización → Orden de producción → Venta → Entrega.</li>
        <li><strong>¿Cómo acepto una cotización?</strong> Usa <strong>Validar y aceptar</strong>. Si faltan datos, el sistema te lista qué corregir.</li>
        <li><strong>¿Puedo cotizar solo con prospecto?</strong> Sí. El cliente se crea al generar la venta.</li>
        <li><strong>¿Por qué no aparece “Crear OP”?</strong> La cotización debe estar validada y aceptada, con trabajos de planta pendientes.</li>
        <li><strong>¿Por qué no aparece “Crear venta”?</strong> Debe existir la OP y estar <strong>Completada</strong> en planta (no basta con crearla).</li>
        <li><strong>¿Me avisan cuando termina planta?</strong> Sí: ventas, gerencia y administradores ven un aviso en la campana del panel (si están dentro de la app se actualiza sola).</li>
        <li><strong>¿Cuándo entrego?</strong> Después de confirmar la venta. Con OP: espera Completada. Sin OP (stock): ya aparece en Entregas. Usa <strong>Comercial → Entregas</strong> o Entrega final en la OP.</li>
        <li><strong>¿Qué es “Atrasada”?</strong> Factura con OP y fecha pactada vencida, aún sin entregar todo. Las de stock no usan esa pestaña.</li>
        <li><strong>¿La venta cambia de estado al entregar?</strong> No: sigue Confirmada; se guarda la fecha de entrega (en la OP o en la venta si no hay OP).</li>
        <li><strong>¿Me avisan al entregar?</strong> Sí: campana para vendedor/gerencia/admin relacionados.</li>
        <li><strong>¿Dónde va el anticipo?</strong> En la venta (formulario o punto de venta). El saldo se calcula solo.</li>
        <li><strong>¿El prospecto ganado sigue en el embudo?</strong> No. Sale de Activos; queda en Convertidos y en Clientes.</li>
        <li><strong>¿Si el cliente ya existía?</strong> Al ganar o al crear venta se vincula por correo/teléfono/NIT; no se duplica.</li>
        <li><strong>¿Por qué no puedo ganar en “Nuevo”?</strong> Solo desde Propuesta o Negociación.</li>
        <li><strong>¿Qué ve el operario?</strong> Inicio con métricas de planta, Órdenes (consulta) y Escaneo. No ve precios ni auditoría.</li>
        <li><strong>¿Qué lleva el QR de etapa?</strong> Una URL de escaneo. El de la orden identifica el código OP-….</li>
        <li><strong>¿Excel no importa?</strong> Guárdalo como CSV UTF-8 con el formato de Clientes.</li>
        <li><strong>¿Dónde está este tutorial?</strong> Menú del avatar → Tutorial, o Ayuda.</li>
    </ul>
@endif
