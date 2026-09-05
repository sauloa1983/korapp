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
                <p>Configurar la empresa, cargar inventario, gestionar clientes, vender, producir, comprar y revisar reportes.</p>
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
                <p><strong>Administrador:</strong> configura todo. <strong>Vendedor:</strong> CRM y ventas. <strong>Operario:</strong> escaneo en planta.</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'arranque')
    <p class="saas-guide-intro">Si vas a llenar Korapp por primera vez, sigue este orden:</p>
    <ol class="saas-guide-roadmap">
        <li><strong>Empresa y usuarios</strong><span>Logo, colores y cuentas del equipo</span></li>
        <li><strong>Inventario</strong><span>Bodegas → categorías → artículos</span></li>
        <li><strong>Clientes</strong><span>Alta manual o importación CSV</span></li>
        <li><strong>Proveedores</strong><span>Antes de registrar compras</span></li>
        <li><strong>Procesos</strong><span>Etapas de producción (corte, ensamble…)</span></li>
        <li><strong>Operación diaria</strong><span>Prospectos, cotizaciones, ventas, compras y OP</span></li>
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
                <p>Indica si la empresa cobra IVA y el porcentaje. Se aplica en ventas, cotizaciones, compras y punto de venta.</p>
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
                <p class="saas-guide-where">Dónde: Seguridad → Usuarios</p>
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
                <p class="saas-guide-where">Dónde: Seguridad → Roles</p>
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
                <p class="saas-guide-where">Dónde: Gestión → Clientes</p>
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
                <h4>NIT / documento</h4>
                <p>Es necesario para confirmar pedidos formales. Sin documento el cliente queda incompleto para ventas.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Reasignar cartera</h4>
                <p>Pasa clientes (y opcionalmente prospectos) de un vendedor a otro.</p>
                <p class="saas-guide-where">Dónde: Gestión → Reasignar cartera</p>
                @if ($showLinks && ! empty($links['reasignar']))
                    <a href="{{ $links['reasignar'] }}" class="saas-guide-link">Abrir Reasignar</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'ventas')
    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Panel y prospectos</h4>
                <p>Revisa el panel comercial y trabaja prospectos en pestañas: Activos, Convertidos, Perdidos.</p>
                <p class="saas-guide-where">Dónde: Ventas → Panel / Prospectos</p>
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
                <h4>Embudo</h4>
                <p>Arrastra entre etapas. En Propuesta/Negociación usa <strong>Ganar</strong> (crea o vincula Cliente) o <strong>Perder</strong>.</p>
                <p class="saas-guide-where">Dónde: Ventas → Embudo de ventas</p>
                @if ($showLinks && ! empty($links['embudo']))
                    <a href="{{ $links['embudo'] }}" class="saas-guide-link">Abrir Embudo</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Agenda</h4>
                <p>Programa visitas o llamadas y márcalas como realizadas.</p>
                <p class="saas-guide-where">Dónde: Ventas → Agenda comercial</p>
                @if ($showLinks && ! empty($links['agenda']))
                    <a href="{{ $links['agenda'] }}" class="saas-guide-link">Abrir Agenda</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Cotización</h4>
                <p>Crea la cotización con <strong>Cliente</strong> o solo con <strong>Prospecto</strong>, envía el PDF y márcala como <strong>Aceptada</strong> cuando el negocio cierre.</p>
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
                <p>Después de aceptar la cotización sigue el flujo <strong>OP → Venta → Entrega</strong> (lo ves en la lección Pedido completo).</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'pedido')
    <div class="saas-guide-callout" style="margin-bottom:1rem;padding:.85rem 1rem;border:1px solid var(--saas-border,#e5e7eb);border-radius:.75rem;background:#f8fafc;">
        <p style="margin:0;font-weight:650;">Flujo oficial del pedido</p>
        <p style="margin:.35rem 0 0;color:#475569;">Cotización → Orden de producción (OP) → Venta / Factura → Entrega final</p>
    </div>

    <div class="saas-guide-steps">
        <div class="saas-guide-step">
            <span>1</span>
            <div>
                <h4>Cotización aceptada</h4>
                <p>Puede ser a un <strong>Cliente</strong> o solo a un <strong>Prospecto</strong> (sin cliente todavía). Incluye productos terminados si vas a fabricar.</p>
                <p class="saas-guide-where">Dónde: Ventas → Cotizaciones</p>
                @if ($showLinks && ! empty($links['cotizaciones']))
                    <a href="{{ $links['cotizaciones'] }}" class="saas-guide-link">Abrir Cotizaciones</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>2</span>
            <div>
                <h4>Crear OP</h4>
                <p>En la cotización usa <strong>Crear OP</strong> y marca los procesos de planta (Impresión, Láser, etc.). Imprime la OP con <strong>Imprimir OP</strong>.</p>
                <p class="saas-guide-where">Dónde: Cotizaciones → Crear OP · Producción → Órdenes</p>
                @if ($showLinks)
                    <div class="saas-guide-actions">
                        @if (! empty($links['cotizaciones']))
                            <a href="{{ $links['cotizaciones'] }}" class="saas-guide-link">Cotizaciones</a>
                        @endif
                        @if (! empty($links['ordenes']))
                            <a href="{{ $links['ordenes'] }}" class="saas-guide-link saas-guide-link--ghost">Órdenes</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Fabricar en planta</h4>
                <p>El operario escanea el QR de cada etapa. Cuando todas terminan, la OP queda <strong>Completada</strong> y entra el producto al inventario.</p>
                <p class="saas-guide-where">Dónde: Producción → Escaneo / Etiquetas QR</p>
                @if ($showLinks && ! empty($links['escaneo']))
                    <a href="{{ $links['escaneo'] }}" class="saas-guide-link">Abrir Escaneo</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Crear venta / factura</h4>
                <p>Vuelve a la cotización → <strong>Crear venta</strong>. Se vinculan las OP. Si solo había prospecto, aquí se crea el <strong>Cliente</strong> automáticamente.</p>
                <p class="saas-guide-where">Dónde: Ventas → Cotizaciones → Crear venta</p>
                @if ($showLinks && ! empty($links['ventas']))
                    <a href="{{ $links['ventas'] }}" class="saas-guide-link">Abrir Ventas</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>5</span>
            <div>
                <h4>Confirmar y entregar</h4>
                <p>Confirma la venta (factura / inventario). En la OP completada usa <strong>Entrega final</strong> para marcarla como Entregada.</p>
                <p class="saas-guide-where">Dónde: Ventas → Confirmar · Producción → Órdenes → Entrega final</p>
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

@if ($lessonKey === 'produccion')
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
                <p>La OP se crea desde la cotización aceptada (<strong>Crear OP</strong>). Luego imprime <strong>Imprimir OP</strong>, <strong>QR / Barras</strong> o Etiquetas.</p>
                <p class="saas-guide-where">Dónde: Cotizaciones · Producción → Órdenes</p>
                @if ($showLinks && ! empty($links['ordenes']))
                    <a href="{{ $links['ordenes'] }}" class="saas-guide-link">Abrir Órdenes</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>3</span>
            <div>
                <h4>Escaneo en planta</h4>
                <p>El operario escanea el QR de la etapa: primer escaneo inicia, segundo finaliza. Puede usar PIN.</p>
                <p class="saas-guide-where">Dónde: QR de etapa o Producción → Escaneo</p>
                @if ($showLinks && ! empty($links['escaneo']))
                    <a href="{{ $links['escaneo'] }}" class="saas-guide-link">Abrir Escaneo</a>
                @endif
            </div>
        </div>
        <div class="saas-guide-step">
            <span>4</span>
            <div>
                <h4>Entrega final</h4>
                <p>Cuando la OP está Completada, usa <strong>Entrega final</strong> para cerrar el ciclo con el cliente.</p>
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
                <h4>Auditoría</h4>
                <p>Consulta quién hizo qué cambio. Solo lectura.</p>
                <p class="saas-guide-where">Dónde: Auditoría</p>
                @if ($showLinks && ! empty($links['auditoria']))
                    <a href="{{ $links['auditoria'] }}" class="saas-guide-link">Abrir Auditoría</a>
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
                <p>Prospecto → Cotización (con prospecto) → Crear OP → Fabricar → Crear venta (se crea el Cliente) → Confirmar → Entrega final.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>B</span>
            <div>
                <h4>Cliente → Pedido</h4>
                <p>Cliente → Cotización → Crear OP → Fabricar → Crear venta → Confirmar → Entrega final.</p>
            </div>
        </div>
        <div class="saas-guide-step">
            <span>C</span>
            <div>
                <h4>Compra → Inventario</h4>
                <p>Proveedor → Compra → Recibir → revisar Movimientos y existencias.</p>
            </div>
        </div>
    </div>
@endif

@if ($lessonKey === 'faq')
    <ul class="saas-guide-faq">
        <li><strong>¿Cuál es el orden del pedido?</strong> Cotización → OP → Venta/Factura → Entrega final.</li>
        <li><strong>¿Puedo cotizar solo con prospecto?</strong> Sí. El cliente se crea al generar la venta.</li>
        <li><strong>¿Por qué no aparece “Crear venta”?</strong> Primero debes crear la OP de los productos terminados.</li>
        <li><strong>¿El prospecto ganado sigue en el embudo?</strong> No. Sale de Activos; queda en Convertidos y en Clientes.</li>
        <li><strong>¿Si el cliente ya existía?</strong> Al ganar o al crear venta se vincula por correo/teléfono/NIT; no se duplica.</li>
        <li><strong>¿Por qué no puedo ganar en “Nuevo”?</strong> Solo desde Propuesta o Negociación.</li>
        <li><strong>¿Qué lleva el QR de etapa?</strong> Una URL de escaneo. El de la orden identifica el código OP-….</li>
        <li><strong>¿Excel no importa?</strong> Guárdalo como CSV UTF-8 con el formato de Clientes.</li>
        <li><strong>¿Dónde está este tutorial?</strong> Menú → Ayuda (Tutorial de uso), o menú del avatar.</li>
    </ul>
@endif
