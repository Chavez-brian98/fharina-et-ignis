<?php

/**
 * Rostros de los empleados para el reconocimiento facial del quiosco.
 *
 * Lo que se guarda NO es una foto: es el vector de 128 flotantes que
 * FaceRecognitionNet calcula en el navegador. Ese embedding es lo que se
 * compara, y por eso alcanza con uno por empleado (uq_face_employee).
 *
 * La comparacion se hace ACA, en el servidor, y no en el navegador: si el
 * matching corriera en el cliente habria que mandar todos los descriptores al
 * dispositivo del quiosco, que es justamente el dato que no queremos regalar.
 * Al servidor viaja solo el descriptor de la persona que esta frente a la
 * camara, que ademas se descarta al terminar la peticion.
 *
 * Distancia euclidiana menor = mas parecidas. El umbral vive en
 * UMBRAL_EUCLIDEANO y hay que calibrarlo con capturas reales: el 0.60 de la
 * documentacion de face-api es un punto de partida, no una garantia.
 */
class EmpleadoFace
{
    private $conn;

    /** Dimension del vector de FaceRecognitionNet. */
    const DIMENSION = 128;

    /**
     * Distancia maxima para aceptar un rostro como el mismo.
     * Debajo de ~0.45 suele ser la misma persona; por encima de ~0.70 hay que
     * desconfiar. 0.60 es el punto medio recomendado por face-api.
     */
    const UMBRAL_EUCLIDEANO = 0.60;

    /**
     * Nombre de la red usada; se guarda con cada rostro para invalidar si cambia.
     *
     * IMPORTANTE: la libreria del navegador y los pesos tienen que ser los de
     * @vladmandic/face-api. Los de justadudewhohacks/face-api.js 0.22.x usan
     * nombres de shard distintos (model-weights_manifest.json + *-shard1.bin)
     * y NO son intercambiables: cargar unos pesos con la otra libreria deja el
     * detector mudo sin ningun error visible.
     */
    const MODELO = 'vladmandic/face-api@1.7.15/face_recognition';

    /** UMD de la libreria (expone window.faceapi). */
    const LIB_URI = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.min.js';

    /** Carpeta con los pesos (.bin + manifest) que consume LIB_URI. */
    const MODELOS_URI = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Valida el vector que llega del navegador. Rechaza cualquier cosa que no
     * sean 128 numeros finitos: un descriptor truncado compararia "casi" y
     * dariia falsos positivos silenciosos.
     *
     * @return array|null Vector normalizado, o null si no es valido.
     */
    public static function normalizar($descriptor)
    {
        if (is_string($descriptor)) {
            $descriptor = json_decode($descriptor, true);
        }
        if (!is_array($descriptor) || count($descriptor) !== self::DIMENSION) {
            return null;
        }

        $out = [];
        foreach ($descriptor as $valor) {
            if (!is_numeric($valor)) {
                return null;
            }
            $valor = (float) $valor;
            if (!is_finite($valor)) {
                return null;
            }
            $out[] = $valor;
        }

        return $out;
    }

    /**
     * Alta o re-captura del rostro de un empleado (upsert por employee_id).
     * Un supervisor puede rehacerla las veces que haga falta.
     */
    public function registrar($employeeId, array $descriptor, $calidad = null, $modelo = self::MODELO)
    {
        $sql = "INSERT INTO empleado_faces (employee_id, descriptor, model, quality)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    descriptor = VALUES(descriptor),
                    model = VALUES(model),
                    quality = VALUES(quality),
                    created_at = CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
        // Sin flags: con serialize_precision = -1 (default de PHP 7.1+) json_encode
        // ya emite la representacion mas corta que vuelve al mismo float, que
        // es justo lo que necesita el comparador de coseno.
        $stmt->bindValue(2, json_encode(array_values($descriptor)));
        $stmt->bindValue(3, $modelo);
        $stmt->bindValue(4, $calidad === null ? null : (float) $calidad);
        $stmt->execute();

        return $this->deEmpleado($employeeId);
    }

    /** Rostros capturados de un empleado, o null si todavia no se enroló. */
    public function deEmpleado($employeeId)
    {
        $stmt = $this->conn->prepare(
            "SELECT id, employee_id, descriptor, model, quality, created_at
             FROM empleado_faces WHERE employee_id = ?"
        );
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /** Solo el descriptor decodificado, o null. */
    public function descriptorDe($employeeId)
    {
        $fila = $this->deEmpleado($employeeId);
        if (!$fila) {
            return null;
        }

        return self::normalizar($fila['descriptor']);
    }

    public function tieneRostro($employeeId)
    {
        return $this->deEmpleado($employeeId) !== null;
    }

    /**
     * Cuantos empleados activos tienen rostro. Es lo unico que el quiosco
     * muestra: mantiene todo el listo para el pantalla sin traer los
     * descriptores a memoria.
     */
    public function enroladosActivos()
    {
        $stmt = $this->conn->query(
            "SELECT COUNT(*) AS total
             FROM empleado_faces f
             JOIN empleados e ON e.id = f.employee_id
             WHERE e.status = 'active'"
        );
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($fila['total'] ?? 0);
    }

    public function eliminar($employeeId)
    {
        $stmt = $this->conn->prepare("DELETE FROM empleado_faces WHERE employee_id = ?");
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Todos los rostros activos con su nombre, para comparar en memoria.
     * Solo lectura de datos biometricos: el que escribe es registrar().
     */
    public function todos()
    {
        $stmt = $this->conn->query(
            "SELECT f.employee_id, f.descriptor, f.model, f.quality,
                    e.name, e.last_name, e.status AS empleado_status
             FROM empleado_faces f
             JOIN empleados e ON e.id = f.employee_id
             WHERE e.status = 'active'"
        );

        $lista = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $descriptor = self::normalizar($fila['descriptor']);
            if ($descriptor === null) {
                continue;
            }
            $fila['descriptor'] = $descriptor;
            $lista[] = $fila;
        }

        return $lista;
    }

    /** Distancia euclidiana entre dos vectores de la misma dimension. */
    public static function distancia(array $a, array $b)
    {
        $suma = 0.0;
        foreach ($a as $i => $valor) {
            $d = $valor - ($b[$i] ?? 0.0);
            $suma += $d * $d;
        }

        return sqrt($suma);
    }

    /** Coseno: 1.0 identico, 0.0 nada que ver. Complementa a la distancia. */
    public static function similitud(array $a, array $b)
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        foreach ($a as $i => $valor) {
            $otro = $b[$i] ?? 0.0;
            $dot += $valor * $otro;
            $na += $valor * $valor;
            $nb += $otro * $otro;
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * Busca el rostro mas parecido entre los enrolados.
     *
     * @return array|null ['employee_id','nombre','distancia','similitud'] o null
     *                     si nadie baja del umbral.
     */
    public function buscar(array $descriptor, $umbral = self::UMBRAL_EUCLIDEANO, $modelo = null)
    {
        $mejor = null;
        $mejorDistancia = INF;

        foreach ($this->todos() as $fila) {
            // Descriptors de redes distintas no son comparables: se ignoran.
            if ($modelo !== null && $fila['model'] !== $modelo) {
                continue;
            }
            $distancia = self::distancia($descriptor, $fila['descriptor']);
            if ($distancia >= $mejorDistancia) {
                continue;
            }
            $mejorDistancia = $distancia;
            $mejor = $fila;
        }

        if ($mejor === null || $mejorDistancia > $umbral) {
            return null;
        }

        return [
            'employee_id' => (int) $mejor['employee_id'],
            'nombre' => trim($mejor['name'] . ' ' . $mejor['last_name']),
            'distancia' => round($mejorDistancia, 4),
            'similitud' => round(self::similitud($descriptor, $mejor['descriptor']), 4),
        ];
    }

    /** Texto corto de la confianza, para mostrar en pantalla. */
    public static function confianzaTexto($similitud)
    {
        $pct = (int) round($similitud * 100);

        return $pct >= 75 ? "Coincidencia {$pct}%" : "Coincidencia {$pct}% (revisar)";
    }
}