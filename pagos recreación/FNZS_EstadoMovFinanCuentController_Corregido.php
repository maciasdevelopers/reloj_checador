<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MovimientosBancariosModelo;

class FNZS_EstadoMovFinanCuentController extends Controller
{
    /**
     * CORRECCIÓN DE LÓGICA DE SALDO:
     * Para cuentas de Activo (Bancos/Caja):
     * - El DEBE (Suma 'S') aumenta el saldo.
     * - El HABER (Retiro 'R') disminuye el saldo.
     *
     * Independientemente de si el movimiento es para un Proveedor, Cliente o Acreedor,
     * lo que importa para el estado de cuenta bancario es si el dinero Entra o Sale.
     */

    public function saldoInicialCuentaByToken($token_cuenta, $fechaInicio)
    {
        return DB::table("fnzs_actividad_movimientos AS movim")
            ->join("fnzs_catalogos_cuentas AS cuent", "movim.cuenta_bancaria", "=", "cuent.id")
            ->leftJoin("fnzs_cobros_cobro AS cobrar", "movim.cobro", "=", "cobrar.id")
            ->leftJoin("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
            ->leftJoin("fnzs_catalogos_cuentas_ajustes AS ajus", "movim.ajuste", "=", "ajus.id")
            ->where([
                'cuent.token_cuenta' => $token_cuenta,
                'movim.movimiento_cancelado' => FALSE
            ])
            ->where('movim.fecha_contabilizacion_movimiento', '<', $fechaInicio)
            ->select(DB::raw("
                SUM(
                    (CASE WHEN movim.tipo_movimiento = 'S' THEN 1 ELSE -1 END) *
                    COALESCE(movim.monto_aplicado, 0) *
                    COALESCE(movim.tipo_cambio_movimiento, cobrar.tipo_cambio, payment.tipo_cambio, ajus.tipo_cambio, 1.0)
                ) as saldo_inicial
            "))
            ->value('saldo_inicial') ?? 0;
    }

    public function movimientosFinancierosCuentaBancaria(Request $request)
    {
        // ... (Validaciones de empresa y usuario omitidas para brevedad)

        $JwtAuth = new \App\Helpers\JwtAuth();
        $token_cuenta = $request->input('token_cuenta');
        $fechaInicio = strtotime($request->input('periodo_inicio') . " 00:00:00");
        $fechaFin = strtotime($request->input('periodo_fin') . " 23:59:59");

        $queryMovimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
            ->join("fnzs_catalogos_cuentas AS count_cat", "fnzs_actividad_movimientos.cuenta_bancaria", "=", "count_cat.id")
            ->where([
                "count_cat.token_cuenta" => $token_cuenta,
                "emp.empresa_token" => $empresa
            ])
            ->whereBetween("fnzs_actividad_movimientos.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
            ->orderBy('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', 'ASC')
            ->get();

        $saldo_inicial = $this->saldoInicialCuentaByToken($token_cuenta, $fechaInicio);
        $saldo_acumulado = $saldo_inicial;

        $arrayMovimientos = [];
        foreach ($queryMovimientos as $vMov) {
            $monto = (float)$vMov->monto_aplicado;

            // LÓGICA ESTANDARIZADA:
            // S = DEBE (Entrada)
            // R = HABER (Salida)
            $debe = ($vMov->tipo_movimiento == 'S') ? $monto : 0;
            $haber = ($vMov->tipo_movimiento == 'R') ? $monto : 0;

            $saldo_acumulado += ($debe - $haber);

            // Determinar la "Parte Relacionada" (quién recibió o entregó el dinero)
            $parte_relacionada = $this->obtenerParteRelacionada($vMov, $JwtAuth);

            $arrayMovimientos[] = [
                "token_movimiento" => $vMov->token_movimiento,
                "fecha_movimiento" => date('Y-m-d', $vMov->fecha_contabilizacion_movimiento),
                "tipo_movimiento" => $vMov->tipo_movimiento,
                "parte_relacionada" => $parte_relacionada,
                "mov_monto_debe" => $debe,
                "mov_monto_haber" => $haber,
                "mov_monto_saldo" => $saldo_acumulado,
                "observaciones" => $JwtAuth->desencriptar($vMov->observaciones_movimiento),
            ];
        }

        return response()->json([
            "status" => "success",
            "saldo_inicial" => $saldo_inicial,
            "movimientos" => $arrayMovimientos,
            "saldo_final" => $saldo_acumulado
        ]);
    }

    private function obtenerParteRelacionada($vMov, $JwtAuth)
    {
        // Esta función centraliza la búsqueda del nombre del Tercero vinculado
        if ($vMov->acreedor_movimiento) {
            return $this->parteRelMovAcreedor($vMov->acreedor_movimiento, $JwtAuth);
        }
        if ($vMov->deudor_movimiento) {
            return $this->parteRelMovDeudor($vMov->deudor_movimiento, $JwtAuth);
        }
        if ($vMov->pago) {
            // Lógica para buscar en fnzs_pagos_pago y sus vínculos
            return "Pago a Tercero";
        }
        return "N/A";
    }
}
