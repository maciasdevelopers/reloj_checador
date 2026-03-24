<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;

class FNZS_PagoOrdenController extends Controller
{
    /**
     * MEJORA PRINCIPAL: Procesador unificado de movimientos financieros.
     *
     * Esta función evita la redundancia que mencionas. En lugar de registrar un movimiento
     * por cada entidad (pago, acreedor, deudor), registramos UN SOLO movimiento en
     * fnzs_actividad_movimientos que puede estar vinculado a múltiples orígenes.
     *
     * De esta forma, en tu estado de cuenta (EstadoMovFinanCuentController) no verás
     * registros duplicados ni tendrás que usar 'continue' para saltarlos.
     */
    private function registrarActividadFinanciera($datos)
    {
        $JwtAuth = new \App\Helpers\JwtAuth();

        // REGLA DE ORO CONTABLE PARA ACTIVOS:
        // S (Suma/Depósito) -> SIEMPRE Aumenta el saldo del activo.
        // R (Retiro/Gasto)   -> SIEMPRE Disminuye el saldo del activo.
        $tipo = $datos['tipo_movimiento'];

        $folioMovimiento = DB::table('fnzs_actividad_movimientos')
            ->where('empresa', $datos['empresa_id'])
            ->lockForUpdate()
            ->max('folio_movimiento') + 1;
        if (!$folioMovimiento) $folioMovimiento = 1;

        $token_movimiento = $JwtAuth->encriptarToken(
            ($datos['pago_id'] ?? 'N/A'),
            ($datos['acreedor_mov_id'] ?? 'N/A'),
            $folioMovimiento
        );

        return DB::table("fnzs_actividad_movimientos")->insertGetId([
            "token_movimiento" => $token_movimiento,
            "folio_movimiento" => $folioMovimiento,
            "fecha_sistema" => time(),
            "fecha_contabilizacion_movimiento" => $datos['fecha_contabilizacion'],
            "tipo_movimiento" => $tipo,
            "subtipo_movimiento" => $datos['subtipo'] ?? 'C',
            "responsable" => $datos['responsable_id'],
            "caja" => $datos['caja_id'] ?? null,
            "cuenta_bancaria" => $datos['cuenta_id'] ?? null,
            "cuenta_monedero" => $datos['monedero_id'] ?? null,
            "monto_aplicado" => $datos['monto'],
            "pago" => $datos['pago_id'] ?? null, // Puede estar vinculado a un pago...
            "acreedor_movimiento" => $datos['acreedor_mov_id'] ?? null, // ...y SIMULTÁNEAMENTE a un mov de acreedor
            "deudor_movimiento" => $datos['deudor_mov_id'] ?? null,
            "ajuste" => $datos['ajuste_id'] ?? null,
            "empresa" => $datos['empresa_id']
        ]);
    }

    /**
     * Ejemplo de cómo quedaría registraMovimientoAcreedor usando el procesador unificado.
     */
    public function registraMovimientoAcreedor(Request $request)
    {
        // ... (Lógica de autenticación y validación igual)

        DB::beginTransaction();
        try {
            $movi_debe_haber = $parametrosArray["movi_debe_haber"];

            // 1. Registramos en la tabla de acreedores (Control de la deuda)
            $acreedor_mov_id = DB::table("fnzs_catalogo_acreedores_movimientos")->insertGetId([
                "condicion_acree_mov" => ($movi_debe_haber == "debe" ? "R" : "S"),
                // ... campos originales
            ]);

            // 2. Determinamos el flujo de caja real
            // Si el acreedor nos da dinero (Haber), entra a nuestra cuenta (S)
            // Si le pagamos al acreedor (Debe), sale de nuestra cuenta (R)
            $tipo_flujo_caja = ($movi_debe_haber == "haber" ? "S" : "R");

            // 3. Registramos la actividad financiera (UNA SOLA VEZ por cada fuente de dinero)
            if (!empty($parametrosArray["pay_cuenta_bancaria"])) {
                foreach ($parametrosArray["pay_cuenta_bancaria"] as $item) {
                    $this->registrarActividadFinanciera([
                        'empresa_id' => $vEmp->id,
                        'fecha_contabilizacion' => $fecha_contab_unix,
                        'tipo_movimiento' => $tipo_flujo_caja,
                        'responsable_id' => $vEmp->userr,
                        'cuenta_id' => $this->getIdByToken('fnzs_catalogos_cuentas', $item['token_cuenta']),
                        'monto' => $item['monto_aplicar'],
                        'acreedor_mov_id' => $acreedor_mov_id, // Vinculación directa
                        // 'pago_id' => $pago_id_si_existiera // Si este mov también generó un Pago maestro
                    ]);
                }
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Movimiento registrado sin duplicidad']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function getIdByToken($tabla, $token) {
        $columna = ($tabla == 'fnzs_catalogos_caja' ? 'token_caja' : ($tabla == 'fnzs_catalogos_cuentas' ? 'token_cuenta' : 'token_cuentamonedero'));
        return DB::table($tabla)->where($columna, $token)->value('id');
    }
}
