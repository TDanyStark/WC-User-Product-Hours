<?php
if (!defined('ABSPATH')) exit;

class Product_Hours_Handler
{

  public function __construct()
  {
    add_action('woocommerce_order_status_completed', [$this, 'wc_da_guardar_datos_compra']);
    // add_action('woocommerce_remove_cart_item', [$this, 'wc_da_restaurar_horas_al_eliminar'], 10, 2);
  }
  public function wc_da_guardar_datos_compra($order_id)
  {
    wcuph_log('Orden completada. ID de la orden: ' . $order_id);
    $productos_con_horas = WCUPH_Config::get_productos_con_horas();

    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    if (!$user_id) {
      wcuph_log('No se encontró un usuario asociado a la orden. Saliendo...');
      return;
    }

    wcuph_log('Usuario asociado a la orden: ' . $user_id);

    foreach ($order->get_items() as $item) {
      $product_id = $item->get_product_id();
      wcuph_log('Procesando producto ID: ' . $product_id);

      // Solo procesar si el producto está en la lista de productos con horas
      if (in_array($product_id, $productos_con_horas)) {
        wcuph_log('El producto está en la lista de productos con horas.');

        $variation_id = $item->get_variation_id();
        wcuph_log('ID de la variación: ' . $variation_id);

        if ($variation_id) {
          $this->guardar_meta_usuario(
            $user_id,
            $product_id,
            $variation_id
          );
        }
      } else {
        wcuph_log('El producto NO está en la lista de productos con horas.');
      }
    }
  }

  private function guardar_meta_usuario($user_id, $product_id, $variation_id)
  {
    wcuph_log("[DEBUG] Iniciando guardar_meta_usuario para producto: {$product_id}");

    $horas_compradas = $this->obtener_horas_variacion($variation_id);
    wcuph_log('Horas compradas: ' . $horas_compradas);

    if (!$horas_compradas) {
      wcuph_log('No se encontraron horas compradas. Saliendo...');
      return;
    }

    // Obtener horas acumuladas ACTUALIZADO
    $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
    wcuph_log('Horas acumuladas antes: ' . print_r($horas_acumuladas, true));

    // Sumar horas al producto específico
    $horas_acumuladas[$product_id] = isset($horas_acumuladas[$product_id])
      ? $horas_acumuladas[$product_id] + $horas_compradas
      : $horas_compradas;

    wcuph_log('Horas acumuladas después: ' . print_r($horas_acumuladas, true));

    // Guardar estructura actualizada
    update_user_meta(
      $user_id,
      'wc_horas_acumuladas',
      $horas_acumuladas
    );
  }

  private function obtener_horas_variacion($variation_id)
  {
    $variation = wc_get_product($variation_id);
    wcuph_log("[OBTENER_HORAS] Procesando variación ID: {$variation_id}");

    // 1. Intentar extraer del formato "- X" al final del nombre
    $nombre = $variation->get_name();
    wcuph_log("[OBTENER_HORAS] Analizando nombre: {$nombre}");

    // Dividir por guiones y tomar el último segmento
    $partes = explode('-', $nombre);
    $ultimo_segmento = trim(end($partes));
    wcuph_log("[OBTENER_HORAS] Último segmento: {$ultimo_segmento}");

    // Buscar números en el último segmento
    preg_match('/\d+/', $ultimo_segmento, $matches);

    if (!empty($matches[0])) {
      $horas = (int)$matches[0];
      wcuph_log("[OBTENER_HORAS] Horas detectadas en último segmento: {$horas}");
      return $horas;
    }

    // 2. Último recurso: buscar cualquier número en todo el nombre
    preg_match('/\d+/', $nombre, $matches);
    $horas = isset($matches[0]) ? (int)$matches[0] : 0;
    wcuph_log("[OBTENER_HORAS] Último recurso - Número encontrado: {$horas}");

    return $horas;
  }

  // /**
  //  * Restaurar horas al eliminar del carrito
  //  */
  // public function wc_da_restaurar_horas_al_eliminar($cart_item_key, $cart)
  // {
  //   $cart_item = $cart->removed_cart_contents[$cart_item_key];

  //   if (isset($cart_item['horas_deducidas'])) {
  //     $user_id = get_current_user_id();
  //     $product_id = $cart_item['product_id'];

  //     if ($user_id && array_key_exists($product_id, WCUPH_Config::get_relacion_productos())) {
  //       $producto_horas_id = WCUPH_Config::get_relacion_productos()[$product_id];
  //       $horas_acumuladas = wcuph_get_accumulated_hours($user_id);
  //       $horas_actuales = $horas_acumuladas[$producto_horas_id] ?? 0;

  //       $horas_acumuladas[$producto_horas_id] = $horas_actuales + $cart_item['horas_deducidas'];
  //       update_user_meta($user_id, 'wc_horas_acumuladas', $horas_acumuladas);

  //       wcuph_log("Horas restauradas: " . $cart_item['horas_deducidas']);
  //     }
  //   }
  // }
}
