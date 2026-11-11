
<?php
// Aplicacion CRUD compatible con PHP 5.6.23

session_start();
require_once __DIR__ . '/config/database.php';

// Escapar datos antes de mostrarlos en HTML.
function e($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Generar token CSRF para proteger los formularios.
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('openssl_random_pseudo_bytes')) {
        $fuerte = false;
        $bytes = openssl_random_pseudo_bytes(32, $fuerte);

        if ($bytes !== false && $fuerte) {
            $_SESSION['csrf_token'] = bin2hex($bytes);
        }
    }

    // No continuar si no se puede generar un token seguro.
    if (empty($_SESSION['csrf_token'])) {
        http_response_code(500);
        exit('No fue posible generar un token seguro. Verifica la extension OpenSSL de PHP.');
    }
}

$mensaje = '';
$tipoMensaje = '';
$errores = array();
$modoEdicion = false;

$alumnoEditar = array(
    'id' => '',
    'nombre' => '',
    'correo' => '',
    'edad' => ''
);

// Procesar formularios enviados mediante POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tokenRecibido = isset($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    if (!is_string($tokenRecibido) ||
        !hash_equals($_SESSION['csrf_token'], $tokenRecibido)) {
        http_response_code(403);
        exit('Solicitud rechazada: token de seguridad invalido. Recarga la pagina.');
    }

    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // REGISTRAR o ACTUALIZAR
    if ($accion === 'guardar' || $accion === 'actualizar') {

        $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
        $correo = trim(isset($_POST['correo']) ? $_POST['correo'] : '');
        $edadTexto = trim(isset($_POST['edad']) ? $_POST['edad'] : '');

        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (strlen($nombre) > 100) {
            $errores[] = 'El nombre no debe superar 100 bytes.';
        }

        if ($correo === '') {
            $errores[] = 'El correo es obligatorio.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Escribe un correo electronico valido.';
        } elseif (strlen($correo) > 150) {
            $errores[] = 'El correo es demasiado largo.';
        }

        if ($edadTexto === '' ||
            !preg_match('/^[0-9]+$/', $edadTexto)) {
            $errores[] = 'La edad debe ser un numero entero.';
        } else {
            $edad = (int)$edadTexto;

            if ($edad < 1 || $edad > 120) {
                $errores[] = 'La edad debe estar entre 1 y 120.';
            }
        }

        $id = 0;

        if ($accion === 'actualizar') {
            $idTexto = isset($_POST['id']) ? $_POST['id'] : '';

            if (!preg_match('/^[1-9][0-9]*$/', (string)$idTexto)) {
                $errores[] = 'El identificador del alumno no es valido.';
            } else {
                $id = (int)$idTexto;
            }
        }

        if (count($errores) === 0) {
            try {
                if ($accion === 'guardar') {
                    $sql = 'INSERT INTO alumnos (nombre, correo, edad)
                            VALUES (:nombre, :correo, :edad)';

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array(
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':edad' => $edad
                    ));

                    $_SESSION['mensaje'] = 'Alumno registrado correctamente.';
                    $_SESSION['tipo_mensaje'] = 'exito';

                } else {
                    $sql = 'UPDATE alumnos
                            SET nombre = :nombre,
                                correo = :correo,
                                edad = :edad
                            WHERE id = :id';

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array(
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':edad' => $edad,
                        ':id' => $id
                    ));

                    $_SESSION['mensaje'] = 'Alumno actualizado correctamente.';
                    $_SESSION['tipo_mensaje'] = 'exito';
                }

                header('Location: index.php');
                exit;

            } catch (PDOException $ex) {
                error_log($ex->getMessage());

                if ($ex->getCode() === '23000') {
                    $errores[] = 'Ese correo ya esta registrado.';
                } else {
                    $errores[] = 'No fue posible guardar los datos.';
                }
            }
        }

        // Conservar los datos si la validacion falla.
        $alumnoEditar = array(
            'id' => $id,
            'nombre' => $nombre,
            'correo' => $correo,
            'edad' => $edadTexto
        );

        if ($accion === 'actualizar') {
            $modoEdicion = true;
        }
    }

    // ELIMINAR
    elseif ($accion === 'eliminar') {
        $idTexto = isset($_POST['id']) ? $_POST['id'] : '';

        if (!preg_match('/^[1-9][0-9]*$/', (string)$idTexto)) {
            $errores[] = 'El identificador del alumno no es valido.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'DELETE FROM alumnos WHERE id = :id'
                );

                $stmt->execute(array(':id' => (int)$idTexto));

                $_SESSION['mensaje'] = $stmt->rowCount() > 0
                    ? 'Alumno eliminado correctamente.'
                    : 'No se encontro el alumno.';

                $_SESSION['tipo_mensaje'] = 'exito';

                header('Location: index.php');
                exit;

            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $errores[] = 'No fue posible eliminar el alumno.';
            }
        }
    }
}

// Mostrar mensaje temporal despues de una operacion.
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipoMensaje = isset($_SESSION['tipo_mensaje'])
        ? $_SESSION['tipo_mensaje']
        : 'exito';

    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// Cargar alumno que se desea editar.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
    $idTexto = $_GET['editar'];

    if (preg_match('/^[1-9][0-9]*$/', (string)$idTexto)) {
        $stmt = $pdo->prepare(
            'SELECT id, nombre, correo, edad
             FROM alumnos WHERE id = :id'
        );

        $stmt->execute(array(':id' => (int)$idTexto));
        $registro = $stmt->fetch();

        if ($registro) {
            $alumnoEditar = $registro;
            $modoEdicion = true;
        } else {
            $errores[] = 'El alumno solicitado no existe.';
        }
    } else {
        $errores[] = 'El identificador del alumno no es valido.';
    }
}

// CONSULTAR todos los alumnos.
$stmt = $pdo->query(
    'SELECT id, nombre, correo, edad, creado_en
     FROM alumnos
     ORDER BY id DESC'
);

$alumnos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD de alumnos</title>
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
    <main class="contenedor">
        <header class="encabezado">
            <h1>Gestion de alumnos</h1>
            <p>Practica educativa de operaciones CRUD con PHP y MySQL</p>
        </header>

        <?php if ($mensaje !== ''): ?>
            <div class="alerta <?php echo e($tipoMensaje); ?>">
                <?php echo e($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if (count($errores) > 0): ?>
            <div class="alerta error">
                <strong>Revisa lo siguiente:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="panel">
            <h2>
                <?php echo $modoEdicion
                    ? 'Editar alumno'
                    : 'Registrar alumno'; ?>
            </h2>

            <form method="post" action="index.php">
                <input type="hidden" name="csrf_token"
                    value="<?php echo e($_SESSION['csrf_token']); ?>">

                <input type="hidden" name="accion"
                    value="<?php echo $modoEdicion
                        ? 'actualizar'
                        : 'guardar'; ?>">

                <?php if ($modoEdicion): ?>
                    <input type="hidden" name="id"
                        value="<?php echo e($alumnoEditar['id']); ?>">
                <?php endif; ?>

                <div class="campo">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre"
                        maxlength="100" required
                        value="<?php echo e($alumnoEditar['nombre']); ?>">
                </div>

                <div class="campo">
                    <label for="correo">Correo electronico</label>
                    <input type="email" id="correo" name="correo"
                        maxlength="150" required
                        value="<?php echo e($alumnoEditar['correo']); ?>">
                </div>

                <div class="campo">
                    <label for="edad">Edad</label>
                    <input type="number" id="edad" name="edad"
                        min="1" max="120" required
                        value="<?php echo e($alumnoEditar['edad']); ?>">
                </div>

                <div class="acciones">
                    <button type="submit" class="boton principal">
                        <?php echo $modoEdicion
                            ? 'Guardar cambios'
                            : 'Registrar alumno'; ?>
                    </button>

                    <?php if ($modoEdicion): ?>
                        <a href="index.php" class="boton secundario">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2>Listado de alumnos</h2>
            <p>Total de registros: <?php echo count($alumnos); ?></p>

            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Edad</th>
                            <th>Fecha de registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($alumnos) === 0): ?>
                            <tr>
                                <td colspan="6">No hay alumnos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alumnos as $alumno): ?>
                                <tr>
                                    <td><?php echo e($alumno['id']); ?></td>
                                    <td><?php echo e($alumno['nombre']); ?></td>
                                    <td><?php echo e($alumno['correo']); ?></td>
                                    <td><?php echo e($alumno['edad']); ?></td>
                                    <td><?php echo e($alumno['creado_en']); ?></td>
                                    <td class="acciones-tabla">
                                        <a class="boton pequeno"
                                           href="index.php?editar=<?php
                                           echo e($alumno['id']); ?>">
                                            Editar
                                        </a>

                                        <form method="post" action="index.php"
                                              class="form-eliminar"
                                              onsubmit="return confirmarEliminacion();">
                                            <input type="hidden" name="csrf_token"
                                                value="<?php
                                                echo e($_SESSION['csrf_token']); ?>">

                                            <input type="hidden" name="accion"
                                                value="eliminar">

                                            <input type="hidden" name="id"
                                                value="<?php
                                                echo e($alumno['id']); ?>">

                                            <button type="submit"
                                                class="boton pequeno peligro">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer>
            Aplicacion didactica local. Utilizala solo en entornos autorizados.
        </footer>
    </main>

    <script src="assets/app.js"></script>
</body>
</html>