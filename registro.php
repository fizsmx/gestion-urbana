include '../conexion.php';
include '../header.php';

$id = $_GET['id'] ?? null;
$usuario = null;

// Si hay ID, es edición. Buscamos los datos
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
}
?>
<h2><?= $id ? 'Editar' : 'Nuevo' ?> Usuario</h2>
<form action="guardar.php" method="POST" class="card p-4 shadow-sm">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="mb-3">
        <label class="form-label">Nombre Completo</label>
        <input type="text" name="nombre" class="form-control" value="<?= $usuario['nombre'] ?? '' ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Usuario</label>
        <input type="text" name="usuario" class="form-control" value="<?= $usuario['usuario'] ?? '' ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= $usuario['email'] ?? '' ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Contraseña <?= $id ? '(Dejar vacío para no cambiar)' : '' ?></label>
        <input type="password" name="clave" class="form-control" <?= $id ? '' : 'required' ?>>
    </div>

    <button type="submit" class="btn btn-success">Guardar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</form>
<?php include '../footer.php'; ?>
