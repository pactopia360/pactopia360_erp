<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pactopia360 · Carga de FIEL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body{
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#f5f7fb;
            margin:0;
            padding:0;
        }
        .wrap{
            max-width:520px;
            margin:60px auto;
            background:#fff;
            border-radius:10px;
            padding:28px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }
        h1{
            margin:0 0 8px 0;
            font-size:22px;
        }
        p{
            margin:0 0 18px 0;
            color:#555;
            font-size:14px;
        }
        .field{
            margin-bottom:16px;
        }
        label{
            display:block;
            font-weight:600;
            margin-bottom:6px;
        }
        input[type=file]{
            width:100%;
        }
        button{
            background:#2563eb;
            color:#fff;
            border:none;
            padding:10px 18px;
            border-radius:6px;
            font-size:14px;
            cursor:pointer;
        }
        .error{
            background:#fee2e2;
            color:#991b1b;
            padding:10px;
            border-radius:6px;
            font-size:13px;
            margin-bottom:14px;
        }
        .note{
            font-size:12px;
            color:#666;
            margin-top:12px;
        }
    </style>
</head>
<body>

<div class="wrap">
    <h1>Carga de archivo FIEL</h1>
    <p>
        Sube el archivo ZIP con la información de la FIEL.
        Este enlace es seguro y tiene vigencia limitada.
    </p>

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $err)
                {{ $err }}<br>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('cliente.sat.fiel.external.upload.store', ['token' => $token]) }}"
        enctype="multipart/form-data"
    >
        @csrf

        <div class="field">
            <label>Archivo ZIP</label>
            <input type="file" name="zip" accept=".zip" required>
        </div>

        <button type="submit">
            Subir archivo
        </button>
    </form>

    <div class="note">
        Solo se permite un archivo ZIP.  
        Tamaño máximo: 50 MB.
    </div>
</div>

</body>
</html>
