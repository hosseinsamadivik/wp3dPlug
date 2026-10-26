# Ordered 3D Print — WooCommerce configurator plugin

I'd build this as a modular WordPress plugin that combines a Three.js product configurator, reusable option templates, dimension-based pricing, customer model uploads, and WooCommerce checkout for both physical prints and downloadable files.

The key design decision is to separate the 3D preview, the configuration, the pricing engine, and the order record. That lets you add new materials, pricing rules, model formats, and printing workflows without rebuilding the entire plugin.
در تمام بخش‌های افزونه از زبان فارسی و چیدمان RTL استفاده می‌کنیم.

بخش

پیاده‌سازی

زبان

فارسی

جهت چیدمان

راست‌چین (RTL)

تقویم

شمسی، در صورت نیاز

اعداد

امکان نمایش فارسی یا لاتین

مبلغ

تومان یا ریال، طبق تنظیم فروشگاه

تاریخ سفارش

نمایش شمسی با حفظ تاریخ استاندارد در پایگاه داده

پیام‌ها

خطاها، ایمیل‌ها و اعلان‌های فارسی

داشبورد

منوها، فرم‌ها، جدول‌ها و فیلترهای فارسی

نمایش سه‌بعدی

رابط فارسی و راست‌چین؛ کنترل‌های چرخش و بزرگ‌نمایی
## 1. Plugin architecture

### WordPress admin

Model library · reusable templates · pricing rules · upload policies

### Product configuration service

Model source · scaling limits · material · colour · finish · sales mode

Three.js viewer

STL / GLB / OBJ preview

Pricing engine

Dimensions · volume · extras

### Validated configuration

Server validates options and computes the authoritative price

### WooCommerce

Cart → checkout → order metadata → fulfillment or download

### Core modules

| Module | Responsibility |
| --- | --- |
| Admin | Manage models, templates, allowed formats and pricing policies |
| Model library | Associate preview files and production files with products |
| Template manager | Reuse material, colour, quality and finish choices |
| Three.js frontend | Render the model, resize it within limits and show dimensions |
| Pricing service | Calculate a quote from validated dimensions and options |
| Upload service | Accept customer files safely and associate them with a configuration |
| Cart integration | Store the selected configuration and price |
| Order integration | Preserve a snapshot of the configuration and production data |
| Digital delivery | Grant access to authorized downloadable files |
| REST API | Return model metadata and calculate validated quotes |

I recommend keeping the initial plugin in PHP with modular classes, using JavaScript modules for the viewer. A separate Python slicing service can be added later if you need accurate print-time estimates.

## 2. Project structure

text

```
ordered-3d-print/
├── ordered-3d-print.php
├── uninstall.php
├── includes/
│   ├── class-plugin.php
│   ├── class-installer.php
│   ├── class-admin.php
│   ├── class-model-repository.php
│   ├── class-template-repository.php
│   ├── class-pricing.php
│   ├── class-rest.php
│   ├── class-upload.php
│   ├── class-cart.php
│   └── class-order.php
├── templates/
│   └── configurator.php
├── assets/
│   ├── js/
│   │   ├── configurator.js
│   │   └── viewer.js
│   └── css/
│       └── configurator.css
└── vendor/
```

For a production release, bundle Three.js using a build tool such as Vite, pin the dependency versions, and generate a local `dist/` bundle. WordPress can then enqueue your compiled assets without requiring browsers to resolve application imports from a CDN.

## 3. Database and product metadata design

Use WordPress post metadata for product-specific settings and a custom post type for reusable templates in the MVP. This is simpler to maintain than introducing custom database tables before you know the scale of your catalog. WordPress recommends choosing storage based on the type and lifecycle of the data.

WordPress Developer Resources

+1

### Product metadata

Store these fields on each WooCommerce product:

| Meta key | Example | Purpose |
| --- | --- | --- |
| `_o3d_enabled` | `yes` | Enable configurator |
| `_o3d_model_type` | `preset` | `preset`, `scalable`, or `upload` |
| `_o3d_preview_id` | `125` | Attachment ID for the preview |
| `_o3d_print_file_id` | `126` | Private production-file reference |
| `_o3d_template_id` | `45` | Reusable option template |
| `_o3d_sales_mode` | `both` | `physical`, `digital`, or `both` |
| `_o3d_base_price` | `20.00` | Configurator base price |
| `_o3d_min_scale` | `0.5` | Minimum uniform scale |
| `_o3d_max_scale` | `2.0` | Maximum uniform scale |
| `_o3d_max_dimensions` | JSON | Printer-compatible bounds |
| `_o3d_pricing_mode` | `volume` | Fixed, volume, or dimension |

Keep all monetary values in WooCommerce's store currency and normalize them to decimal values when processing them. Do not store a client-computed total as authoritative metadata.

### Reusable option template

Create a custom post type named `o3d_template`. Each template has a structured configuration document.

json

```
{
  "schema_version": 1,
  "options": [
    {
      "key": "material",
      "label": "Material",
      "type": "select",
      "required": true,
      "choices": [
        {
          "value": "pla",
          "label": "PLA",
          "price_delta": 0
        },
        {
          "value": "petg",
          "label": "PETG",
          "price_delta": 3
        }
      ]
    },
    {
      "key": "finish",
      "label": "Finish",
      "type": "select",
      "required": true,
      "choices": [
        {
          "value": "raw",
          "label": "Unfinished",
          "price_delta": 0
        },
        {
          "value": "sanded",
          "label": "Sanded",
          "price_delta": 6
        }
      ]
    }
  ]
}
```

This is a template schema example, not an instruction to accept arbitrary JSON from shoppers. The admin editor should validate and save it through WordPress APIs.

Support `select`, `radio`, `colour`, `checkbox`, and eventually numeric fields. Use stable internal keys for options and choices so that changing a display label doesn't invalidate old orders.

### Configuration snapshots and uploaded models

A cart item should hold a configuration snapshot containing:

json

```
{
  "schema_version": 1,
  "product_id": 250,
  "model_revision": "rev-3",
  "dimensions_mm": {
    "x": 100,
    "y": 65,
    "z": 40
  },
  "options": {
    "material": "petg",
    "finish": "sanded"
  },
  "sales_mode": "physical",
  "upload_id": null
}
```

At checkout, copy a validated snapshot to the order line item. This ensures an order retains the choices and dimensions that were purchased even if the shop owner subsequently changes the template.

For customer-uploaded models, `upload_id` should be an opaque server-generated identifier, not a raw filesystem path or unrestricted URL.

## 4. Pricing engine design

Use a deterministic PHP pricing service with a documented pricing policy. Three.js supplies the dimensions shown in the interface, but PHP independently validates the requested scale against trusted model metadata.

### Three pricing modes

- Dimension pricing: charge according to length, bounding-box dimensions, or a defined size tier.
- Volume pricing: calculate a price based on the scaled model's estimated geometric volume.
- Fixed plus options: use a base price and add the selected option charges.

For example, a simple volume-based policy could be:

$$
P = P_{\text{base}} + r_v V + \sum_i \Delta_i
$$

where:

- $P_{\text{base}}$ is the base price.
- $r_v$ is the configured price per cubic centimetre.
- $V$ is the scaled model volume in cubic centimetres.
- $\Delta_i$ is the additional price for each selected option.

For a uniform scale factor $s$, volume changes by $s^3$. A model scaled by 1.5 has 3.375 times the original geometric volume. That does not necessarily mean its printing cost rises by the same amount.

If the price is intended to reflect actual production costs, add material usage, infill, support structures, print time, waste, machine overhead and a minimum charge in a later version.

### Recommended pricing policy

Store the following per product or template:

json

```
{
  "mode": "volume",
  "base_price": 10,
  "price_per_cm3": 0.08,
  "minimum_price": 15,
  "maximum_price": 500,
  "currency": "GBP",
  "rounding": "store_currency"
}
```

The currency is descriptive configuration; the WooCommerce store remains authoritative for actual currency, taxes and formatting.

Important geometry distinction: bounding-box volume (`x × y × z`) is not the same as the volume of the actual solid. If you want volume pricing, calculate the mesh volume from validated geometry or precompute it on the server. For arbitrary customer-uploaded STL files, perform mesh validation and volume calculation in a controlled processing service before accepting the quote. Do not trust dimensions or volume submitted by JavaScript.

## 5. WooCommerce and WordPress hooks

These are the main integration points for the plugin.

| Hook / API | Purpose |
| --- | --- |
| `add_meta_boxes` | Add configurator settings to product editing |
| `save_post_product` | Save and validate product configuration |
| `wp_enqueue_scripts` | Load viewer assets on relevant product pages |
| `woocommerce_before_add_to_cart_button` | Render the configurator for classic product forms |
| `woocommerce_add_to_cart_validation` | Validate submitted configuration |
| `woocommerce_add_cart_item_data` | Attach configuration data to the cart item |
| `woocommerce_before_calculate_totals` | Apply the server-calculated cart price |
| `woocommerce_get_item_data` | Display configuration details in the cart |
| `woocommerce_checkout_create_order_line_item` | Save the order configuration snapshot |
| `rest_api_init` | Register quote and upload endpoints |
| `woocommerce_store_api_register_endpoint_data` | Expose additional cart data to Cart and Checkout blocks where required |

The Store API documentation explicitly identifies the server as the source of truth for cart items, prices and totals. Use its extension mechanisms if you want full compatibility with block-based cart and checkout.

WooCommerce developer docs

+2

Two caveats:

- `woocommerce_before_add_to_cart_button` is a classic product form hook. A separate block-compatible integration may be needed depending on your product-page implementation.
- Cart pricing hooks must be idempotent: every recalculation should set the price from the original product base and validated configuration, not add the extras repeatedly.

## 6. Practical MVP code scaffold

The following scaffold establishes the main extension points. It is a starting implementation rather than a complete, production-ready plugin; upload processing, the full admin template editor and the Store API integration are separate tasks.

### A. Plugin entry point

Create `ordered-3d-print.php`.

php

```
<?php
/**
 * Plugin Name: Ordered 3D Print Configurator
 * Description: Three.js product configuration and pricing for WooCommerce.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 * Text Domain: ordered-3d-print
 */

defined( 'ABSPATH' ) || exit;

define( 'O3D_VERSION', '0.1.0' );
define( 'O3D_PATH', plugin_dir_path( __FILE__ ) );
define( 'O3D_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Ordered 3D Print requires WooCommerce.',
                'ordered-3d-print'
            );
            echo '</p></div>';
        } );
        return;
    }

    require_once O3D_PATH . 'includes/class-pricing.php';
    require_once O3D_PATH . 'includes/class-cart.php';
    require_once O3D_PATH . 'includes/class-order.php';

    O3D_Cart::init();
    O3D_Order::init();
} );
```

For the first release, activate it on a staging site with WooCommerce installed and test with a simple product before introducing custom uploads.

### B. Pricing service

Create `includes/class-pricing.php`.

This version handles fixed base pricing and option surcharges. It intentionally does not calculate arbitrary STL mesh volumes; that requires validated geometry data.

php

```
<?php
defined( 'ABSPATH' ) || exit;

final class O3D_Pricing {

    /**
     * Calculate a quote from trusted product configuration.
     *
     * The $selected values are customer input.
     * The $config values must come from saved admin settings.
     */
    public static function quote( $base_price, array $selected, array $config ) {
        $total = (float) $base_price;
        $normalized = array();

        $options = $config['options'] ?? array();

        foreach ( $options as $option ) {
            $key = $option['key'] ?? '';

            if ( ! is_string( $key ) || '' === $key ) {
                continue;
            }

            $value = $selected[ $key ] ?? null;

            if ( ! empty( $option['required'] )
                && ( null === $value || '' === $value ) ) {
                return new WP_Error(
                    'o3d_missing_option',
                    sprintf( 'Please select %s.', $option['label'] ?? $key )
                );
            }

            if ( null === $value || '' === $value ) {
                continue;
            }

            $found = false;

            foreach ( $option['choices'] ?? array() as $choice ) {
                if ( ! isset( $choice['value'] ) ) {
                    continue;
                }

                // Match stable choice identifiers, not prices supplied
                // by the browser.
                if ( (string) $choice['value'] === (string) $value ) {
                    $delta = (float) ( $choice['price_delta'] ?? 0 );

                    if ( ! is_finite( $delta ) || $delta < 0 ) {
                        return new WP_Error(
                            'o3d_invalid_price',
                            'Invalid option pricing configuration.'
                        );
                    }

                    $total += $delta;
                    $normalized[ $key ] = (string) $choice['value'];
                    $found = true;
                    break;
                }
            }

            if ( ! $found ) {
                return new WP_Error(
                    'o3d_invalid_choice',
                    sprintf( 'Invalid selection for %s.', $key )
                );
            }
        }

        return array(
            'price'   => $total,
            'options' => $normalized,
        );
    }
}
```

This example deliberately restricts option surcharges to non-negative fixed amounts. Add negative adjustments, multipliers and conditional pricing only with explicit validation and clear rounding rules.

### C. Cart integration

Create `includes/class-cart.php`.

The key principle is to store the validated quote in the cart item and apply its price without trusting a posted total.

php

```
<?php
defined( 'ABSPATH' ) || exit;

final class O3D_Cart {

    public static function init() {
        add_filter(
            'woocommerce_add_to_cart_validation',
            array( __CLASS__, 'validate_add' ),
            10, 3
        );

        add_filter(
            'woocommerce_add_cart_item_data',
            array( __CLASS__, 'add_item_data' ),
            10, 4
        );

        add_action(
            'woocommerce_before_calculate_totals',
            array( __CLASS__, 'apply_price' )
        );

        add_filter(
            'woocommerce_get_item_data',
            array( __CLASS__, 'display_item_data' ),
            10, 2
        );
    }

    private static function get_config( $product_id ) {
        $template_id = absint(
            get_post_meta( $product_id, '_o3d_template_id', true )
        );

        if ( ! $template_id || 'o3d_template' !== get_post_type( $template_id ) ) {
            return array( 'options' => array() );
        }

        $config = get_post_meta(
            $template_id, '_o3d_config', true
        );

        return is_array( $config ) ? $config : array( 'options' => array() );
    }

    private static function make_quote( $product_id, $selected ) {
        $product = wc_get_product( $product_id );

        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            return new WP_Error( 'o3d_product', 'Unsupported product.' );
        }

        if ( 'yes' !== get_post_meta( $product_id, '_o3d_enabled', true ) ) {
            return new WP_Error( 'o3d_disabled', 'Configurator is disabled.' );
        }

        return O3D_Pricing::quote(
            (float) $product->get_price(),
            $selected,
            self::get_config( $product_id )
        );
    }

    public static function validate_add( $passed, $product_id, $quantity ) {
        if ( ! $passed ) {
            return false;
        }

        if ( 'yes' !== get_post_meta( $product_id, '_o3d_enabled', true ) ) {
            return $passed;
        }

        $selected = isset( $_POST['o3d_options'] )
            ? wc_clean( wp_unslash( $_POST['o3d_options'] ) )
            : array();

        if ( ! is_array( $selected ) ) {
            wc_add_notice( 'Invalid model configuration.', 'error' );
            return false;
        }

        $quote = self::make_quote( $product_id, $selected );

        if ( is_wp_error( $quote ) ) {
            wc_add_notice( $quote->get_error_message(), 'error' );
            return false;
        }

        return true;
    }

    public static function add_item_data(
        $cart_item_data, $product_id, $variation_id, $quantity
    ) {
        if ( 'yes' !== get_post_meta( $product_id, '_o3d_enabled', true ) ) {
            return $cart_item_data;
        }

        $selected = isset( $_POST['o3d_options'] )
            ? wc_clean( wp_unslash( $_POST['o3d_options'] ) )
            : array();

        if ( ! is_array( $selected ) ) {
            return $cart_item_data;
        }

        $quote = self::make_quote( $product_id, $selected );

        if ( is_wp_error( $quote ) ) {
            return $cart_item_data;
        }

        // A unique key prevents different configurations from being
        // merged into a single cart line.
        $cart_item_data['o3d'] = array(
            'options' => $quote['options'],
            'price'   => $quote['price'],
        );

        $cart_item_data['o3d_unique'] = wp_generate_uuid4();

        return $cart_item_data;
    }

    public static function apply_price( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        if ( ! $cart instanceof WC_Cart ) {
            return;
        }

        foreach ( $cart->get_cart() as $item ) {
            if ( empty( $item['o3d'] ) || ! isset( $item['data'] ) ) {
                continue;
            }

            $price = (float) $item['o3d']['price'];

            if ( is_finite( $price ) && $price >= 0 ) {
                $item['data']->set_price( $price );
            }
        }
    }

    public static function display_item_data( $data, $item ) {
        foreach ( $item['o3d']['options'] ?? array() as $key => $value ) {
            $data[] = array(
                'key'   => sanitize_text_field( $key ),
                'value' => sanitize_text_field( $value ),
            );
        }

        return $data;
    }
}
```

Production hardening required: the scaffold above demonstrates classic add-to-cart flow. Before release, revalidate the quote when restoring a cart from a session, enforce quantity and product availability, include scaling and sales mode in the quote, and support variable products if you use them. Never allow the browser to supply trusted `price`, `volume`, or `dimensions` fields.

For block checkout, extend the Store API cart flow and verify that configuration data is accepted, preserved, and displayed through the Blocks-compatible mechanisms.

WooCommerce developer docs

+1

### D. Order metadata

Create `includes/class-order.php`.

php

```
<?php
defined( 'ABSPATH' ) || exit;

final class O3D_Order {

    public static function init() {
        add_action(
            'woocommerce_checkout_create_order_line_item',
            array( __CLASS__, 'save_item' ),
            10, 4
        );
    }

    public static function save_item(
        $item, $cart_item_key, $values, $order
    ) {
        if ( empty( $values['o3d'] ) || ! is_array( $values['o3d'] ) ) {
            return;
        }

        $config = $values['o3d'];

        // Store the validated configuration for production and support.
        $item->add_meta_data(
            '_o3d_configuration',
            wp_json_encode( $config ),
            true
        );

        foreach ( $config['options'] ?? array() as $key => $value ) {
            $item->add_meta_data(
                '3D Print — ' . sanitize_text_field( $key ),
                sanitize_text_field( $value ),
                true
            );
        }
    }
}
```

This hook covers the classic checkout order-creation flow. Test the equivalent lifecycle for your supported WooCommerce version and Checkout Block setup before relying on it for all orders.

In the final design, save the model revision, validated dimensions, sales mode and an upload reference as well as the option selections. Store private production-file references rather than public filesystem paths.

## 7. Three.js viewer and frontend implementation

Use Three.js for visualization and scaling, but keep the server authoritative for pricing and physical limits. The viewer should support a product's preset model, an uploaded customer model, and a resizable model through a consistent interface.

Three.js provides `STLLoader`, `GLTFLoader` and `OrbitControls` as separate modules.

Three.js Docs

+2

### A. Load a model and calculate its bounding dimensions

Create `assets/js/viewer.js`. This example assumes your build system bundles the Three.js imports.

javascript

```
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

export class O3DViewer {
  constructor(container) {
    this.container = container;
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color('#f6f6f6');

    this.camera = new THREE.PerspectiveCamera(
      45, 1, 0.1, 10000
    );
    this.camera.position.set(120, 100, 160);

    this.renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: false
    });

    this.renderer.setPixelRatio(
      Math.min(window.devicePixelRatio || 1, 2)
    );
    container.appendChild(this.renderer.domElement);

    this.controls = new OrbitControls(
      this.camera,
      this.renderer.domElement
    );
    this.controls.enableDamping = true;

    this.scene.add(
      new THREE.HemisphereLight(0xffffff, 0x777777, 2)
    );

    const light = new THREE.DirectionalLight(0xffffff, 2);
    light.position.set(100, 150, 100);
    this.scene.add(light);

    this.model = null;
    this.resizeObserver = new ResizeObserver(() => this.resize());
    this.resizeObserver.observe(container);

    this.resize();
    this.animate();
  }

  async load(url) {
    const extension = url.split('?')[0].split('.').pop().toLowerCase();

    let object;

    if (extension === 'stl') {
      const geometry = await new STLLoader().loadAsync(url);
      geometry.computeVertexNormals();

      object = new THREE.Mesh(
        geometry,
        new THREE.MeshStandardMaterial({
          color: '#b8bdc5',
          roughness: 0.75
        })
      );
    } else if (extension === 'glb' || extension === 'gltf') {
      const gltf = await new GLTFLoader().loadAsync(url);
      object = gltf.scene;
    } else {
      throw new Error('Unsupported preview format.');
    }

    if (this.model) {
      this.scene.remove(this.model);
      this.disposeObject(this.model);
    }

    this.model = object;
    this.scene.add(object);

    // Center the object and fit the camera.
    const bounds = new THREE.Box3().setFromObject(object);
    const center = bounds.getCenter(new THREE.Vector3());
    object.position.sub(center);

    const size = bounds.getSize(new THREE.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);

    if (!Number.isFinite(maxDim) || maxDim <= 0) {
      throw new Error('The model has invalid dimensions.');
    }

    const fov = THREE.MathUtils.degToRad(this.camera.fov);
    const distance = (maxDim / 2) / Math.tan(fov / 2) * 1.5;

    this.camera.position.set(
      distance, distance * 0.7, distance
    );
    this.camera.near = Math.max(0.01, distance / 1000);
    this.camera.far = distance * 100;
    this.camera.updateProjectionMatrix();

    this.controls.target.set(0, 0, 0);
    this.controls.update();

    return {
      x: size.x,
      y: size.y,
      z: size.z
    };
  }

  setColour(colour) {
    if (!this.model) return;

    this.model.traverse((object) => {
      if (!object.isMesh || !object.material) return;

      const materials = Array.isArray(object.material)
        ? object.material
        : [object.material];

      for (const material of materials) {
        if (material.color) {
          material.color.set(colour);
        }
      }
    });
  }

  resize() {
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;
    if (!width || !height) return;

    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height);
  }

  animate() {
    this.frame = requestAnimationFrame(() => this.animate());
    this.controls.update();
    this.renderer.render(this.scene, this.camera);
  }

  disposeObject(object) {
    object.traverse((child) => {
      if (!child.isMesh) return;
      child.geometry?.dispose();

      const materials = Array.isArray(child.material)
        ? child.material
        : [child.material];

      materials.forEach((material) => material?.dispose());
    });
  }

  destroy() {
    cancelAnimationFrame(this.frame);
    this.resizeObserver.disconnect();
    this.controls.dispose();

    if (this.model) this.disposeObject(this.model);

    this.renderer.dispose();
    this.renderer.domElement.remove();
  }
}
```

This is an MVP viewer. Before using it with untrusted uploads, also enforce download size limits, loading timeouts and geometry complexity limits. GLTF models may reference external resources, and textures may require additional disposal and loading controls.

For production files, I would not expose the original printable asset just because a customer can see the preview. Use separate preview and manufacturing assets, with private storage for manufacturing files where appropriate.

### B. Configure the viewer and options

Your PHP template should render a viewer container and option fields with values generated from the saved template. It should also expose a nonce-protected endpoint for requesting a quote.

An example frontend controller:

javascript

```
import { O3DViewer } from './viewer.js';

const root = document.querySelector('[data-o3d-configurator]');

if (root) {
  const viewer = new O3DViewer(
    root.querySelector('[data-o3d-viewer]')
  );

  const modelUrl = root.dataset.previewUrl;
  const form = root.querySelector('form');
  const priceOutput = root.querySelector('[data-o3d-price]');
  const colourInput = root.querySelector('[name="o3d_colour"]');

  if (modelUrl) {
    viewer.load(modelUrl).catch((error) => {
      console.error(error);
      root.querySelector('[data-o3d-error]').textContent =
        'The 3D preview could not be loaded.';
    });
  }

  colourInput?.addEventListener('input', (event) => {
    viewer.setColour(event.target.value);
  });

  form?.addEventListener('change', async () => {
    const formData = new FormData(form);
    const options = Object.fromEntries(formData.entries());

    // Send option identifiers and requested dimensions only.
    // Never send a client-calculated total as a trusted price.
    // POST to your registered quote endpoint with a WP REST nonce.
    // Display the server-returned quote.
  });
}
```

The quote endpoint should accept a product ID, option identifiers and requested scale or dimensions. It should then retrieve the product's trusted model metadata, enforce limits, calculate the price and return a formatted quote. Use the WordPress REST API and permission callbacks rather than exposing unrestricted admin operations.

WordPress Developer Resources

+1

### C. Scaling rules

For the MVP, use uniform scaling: one scale factor applies to X, Y and Z.

If a source model is 100 × 60 × 40 mm and the customer selects a scale factor of 1.5, the requested dimensions become 150 × 90 × 60 mm.

- Validate the minimum and maximum scale.
- Validate all three resulting dimensions against the printer envelope.
- Prevent negative, zero, infinite and non-numeric values.
- Send the requested scale to the quote endpoint.
- Have the server calculate dimensions and pricing from the trusted original dimensions.

Non-uniform scaling should be a later feature because it can distort mechanical parts and make wall thickness or fit unreliable.

## 8. Customer uploads and digital product delivery

These should be two separate features because a model submitted for printing is not necessarily a model the customer is entitled to download.

### Customer-upload workflow

1. Customer uploads an STL or another supported format.
2. Server checks the upload size, extension, file signature, permissions and processing limits.
3. A background worker validates the mesh, calculates dimensions and volume, and rejects invalid geometry.
4. The validated model receives a server-generated ID and is associated with the quote.
5. At checkout, the order stores that ID and the validated configuration. Staff can retrieve the manufacturing file through an authorized admin workflow.

Recommended security measures:

- Store customer uploads outside the public web root or in private object storage.
- Never trust the submitted MIME type or file extension alone.
- Apply upload size, polygon count, processing time and storage limits.
- Use random identifiers and enforce access permissions for file retrieval.
- Delete abandoned uploads according to a defined retention policy.
- Consider malware scanning and isolation of any geometry-processing service.

A nonce helps protect WordPress requests against CSRF, but it does not replace authorization or file validation.

### Digital models

For products that sell downloadable STL or 3D files, use WooCommerce's downloadable product functionality where possible.

- Enable virtual and downloadable settings for digital-only products.
- Configure WooCommerce download permissions and limits.
- For physical products, use the configurator to capture the manufacturing specification.
- For products offering both, decide explicitly whether the customer is buying a print, a file, or a bundle containing both.

If both physical and digital versions share one product page, I recommend a required Purchase type option. The server must validate it and apply the corresponding product rules. For digital purchases, don't expose the source file through the public Three.js preview URL.

## 9. Development roadmap

Phase 1 — Sell predefined models

Foundation- Product settings and reusable option templates.
- STL/GLB viewer with rotation, zoom and colour preview.
- Fixed-price options and dimension-based scaling.
- Classic cart, checkout and order metadata.

Phase 2 — Custom models and digital sales

- Secure uploads and private file storage.
- Mesh validation and server-side geometry calculations.
- Digital downloads and physical/digital purchase modes.
- Order-management tools for production staff.

Phase 3 — Production-grade quotations

- Slicing integration for material and time estimates.
- Printer profiles, minimum wall thickness and build-volume checks.
- More sophisticated material, waste and machine-cost pricing.
- Full Cart and Checkout Blocks compatibility and automated tests.

## 10. MVP acceptance checklist

Before making the plugin available to customers, test the following:

- [ ]A product can be enabled or disabled independently.
- [ ]Multiple products can reuse the same option template.
- [ ]Invalid or missing required options are rejected.
- [ ]Altering the browser's price does not change the trusted total.
- [ ]Dimensions and volume are validated server-side.
- [ ]Different configurations remain distinct cart items.
- [ ]Cart totals stay correct when quantities change or sessions are restored.
- [ ]Configuration snapshots survive checkout and order creation.
- [ ]Customer uploads cannot be accessed by unauthorized visitors.
- [ ]Digital downloads follow WooCommerce permissions.
- [ ]Classic checkout and Cart/Checkout Blocks are tested separately.
- [ ]The viewer works on mobile and handles invalid or oversized models gracefully.

## Recommended next step

Build the first release around predefined models, reusable templates, uniform scaling, server-validated dimension pricing, and reliable WooCommerce cart/order integration. Then add custom uploads and mesh-based volume pricing once the transactional foundation is stable.

The code above provides the starting classes and viewer structure, but it is not yet an install-and-sell ZIP: the admin editor, REST quote endpoint, private upload processor, template persistence and complete checkout compatibility still need to be implemented and tested.

For the next implementation stage, the most useful deliverable would be a complete plugin scaffold with the admin product panel, reusable template editor, REST quote endpoint, and frontend configurator wired together.