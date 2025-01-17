<?php
/**
 * Funzioni
 *
 * @author ilGhera
 * @package wc-exporter-for-danea/includes
 * @since 1.4.4
 */

/*Evito accesso diretto*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCtoDanea {

	/**
	 * Recupero il valore dell'iva
	 *
	 * @param  int    $product_id l'id del prodotto.
	 * @param  string $type       il tipo di dato da restituire, nome o aliquota.
	 * @return mixed
	 */
	public static function get_tax_rate( $product_id, $type = '' ) {

		$output = 'FC';

		if ( 'yes' === get_option( 'woocommerce_calc_taxes' ) ) {

			$output = 0;

			$tax_status = get_post_meta( $product_id, '_tax_status', true );

			/*In caso di variazione recupero dati del prodotto padre*/
			$parent_id = wp_get_post_parent_id( $product_id );
			$parent_tax_status = $parent_id ? get_post_meta( $parent_id, '_tax_status', true ) : '';

			if ( 'taxable' == $tax_status || ( '' == $tax_status && 'taxable' === $parent_tax_status ) ) {

				/*Valore nullo con iva al 22, controllo necessario in caso di varizione di prodotto*/
				$tax_class = $tax_status ? get_post_meta( $product_id, '_tax_class', true ) : get_post_meta( $parent_id, '_tax_class', true );

				if ( 'parent' === $tax_class && 'taxable' === $parent_tax_status ) {
					$tax_class = get_post_meta( $parent_id, '_tax_class', true );
				}

				global $wpdb;
				$query = "SELECT tax_rate, tax_rate_name FROM " . $wpdb->prefix . "woocommerce_tax_rates WHERE tax_rate_class = '" . $tax_class . "'";

				$results = $wpdb->get_results( $query, ARRAY_A );

				if ( $results ) {
					$output = 'name' === $type ? $results[0]['tax_rate_name'] : intval( $results[0]['tax_rate'] );
				}
			}
		}
		
		return $output;

	}


	/**
	 * Cambia la posizione del singolo termine di tassonomia in base al parent_id
	 *
	 * @param  object $a il termine di tasssonomia.
	 * @param  object $b un altro termine di tassonomia da confrontare.
	 * @return mixed la nuova posizione
	 */
	public static function sort_sub_categories( $a, $b ) {

		if ( isset( $a->parent ) && isset( $b->parent ) ) {

			if ( $a->parent == $b->parent ) {

				return 0;

			}

			return ( $a->parent > $b->parent ) ? +1 : -1;

		}

	}


	/**
	 * Prepara le sottocategorie al download
	 *
	 * @param  array $child i termini di tassonomia.
	 * @return string la lista formattata per Danea Easyfatt
	 */
	public static function prepare_sub_categories( $child, $sku ) { // temp.

		$list = array();

		if ( ! empty( $child ) ) {

			usort( $child, array( 'WCtoDanea', 'sort_sub_categories' ) );

			foreach ( $child as $cat ) {

				$list[] = $cat->slug;

			}

			$child_string = implode( ' >> ', $list );

			return $child_string;

		}


	}


	/**
	 * Ottengo la categoria di appartenenza del prodotto
	 *
	 * @param  int $product_id l'id del prodotto.
	 * @return string
	 */
	public static function get_product_category_name( $product_id ) {

		$parent = null;
		$child  = array();
		$product_cat = get_the_terms( $product_id, 'product_cat' );

		$sku = get_post_meta( $product_id, '_sku', true );

		if ( null != $product_cat ) {

			foreach ( $product_cat as $cat ) {

				if ( 0 != $cat->parent ) {

					$child[] = $cat;

					$get_parent = get_term_by( 'id', $cat->parent, 'product_cat' );
					$parent     = 0 === $get_parent->parent ? $get_parent->slug : $parent;

				} else {

					$parent = null === $parent ? $cat->slug : $parent;

				}

			}

			if ( $child ) {

				$child_string = self::prepare_sub_categories( $child, $sku ); // temp.

				$cat_name = array(
					'cat' => $parent,
					'sub' => $child_string,
				);

			} else {

				$cat_name = array(
					'cat' => $parent,
					'sub' => '',
				);

			}

		} else {

			$cat_name = null;

		}

		return $cat_name;

	}


	/**
	 * URL immagine prodotto
	 *
	 * @return string
	 */
	public static function get_image_product() {

		$thumb_id = get_post_thumbnail_id();
		$thumb_url = wp_get_attachment_image_src( $thumb_id, 200, true );

		return $thumb_url[0];

	}


	/**
	 * Recupero l'autore del corso sensei legato al prodotto woocommerce
	 *
	 * @param  int $product_id l'id del prodotto.
	 */
	public static function get_sensei_author( $product_id ) {

		global $wpdb;
		$query_course = "
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE
			meta_key = '_course_woocommerce_product'
			AND meta_value = $product_id
		";

		$courses = $wpdb->get_results( $query_course );

		if ( null != $courses ) {

			$course_id = get_object_vars( $courses[0] );
			$author = get_post_field( 'post_author', $course_id['post_id'] );

			return $author;

		}

	}


	/**
	 * Definisce come recuperare i campi fiscali, generati dal plugin o attraverso plugin supportati
	 * Anche quelli per la fatturazione elettronica
	 *
	 * @param  string $field il campo da cercare.
	 * @return string il nome del meta_key che verrà utilizzato per recuperare il dato
	 */
	public static function get_italian_tax_fields_names( $field ) {

		$cf_name      = null;
		$pi_name      = null;
		$pec_name     = null;
		$pa_code_name = null;

		/*Campi generati dal plugin*/
		if ( get_option( 'wcexd_company_invoice' ) || get_option( 'wcexd_private_invoice' ) ) {

			$cf_name      = 'billing_wcexd_cf';
			$pi_name      = 'billing_wcexd_piva';
			$pec_name     = 'billing_wcexd_pec';
			$pa_code_name = 'billing_wcexd_pa_code';

		} else {

			/*Plugin supportati*/

			/*WooCommerce Aggiungere CF e P.IVA*/
			if ( class_exists( 'WC_BrazilianCheckoutFields' ) ) {
				$cf_name = 'billing_cpf';
				$pi_name = 'billing_cnpj';
			}

			/*WooCommerce P.IVA e Codice Fiscale per Italia*/
			elseif ( class_exists( 'WooCommerce_Piva_Cf_Invoice_Ita' ) || class_exists( 'WC_Piva_Cf_Invoice_Ita' ) ) {
				$cf_name      = 'billing_cf';
				$pi_name      = 'billing_piva';
				$pec_name     = 'billing_pec';
				$pa_code_name = 'billing_pa_code';
			}

			/*YITH WooCommerce Checkout Manager*/
			elseif ( function_exists( 'ywccp_init' ) ) {
				$cf_name = 'billing_Codice_Fiscale';
				$pi_name = 'billing_Partita_IVA';
			}

			/*WOO Codice Fiscale*/
			elseif ( function_exists( 'woocf_on_checkout' ) ) {
				$cf_name = 'billing_CF';
				$pi_name = 'billing_iva';
			}

			/*WooCommerce Italian Add-on Plus*/
			elseif ( class_exists( 'WooCommerce_Italian_add_on_plus' ) ) {
				$cf_name      = 'billing_cf';
				$pi_name      = 'billing_cf'; // temp.
				$pec_name     = 'billing_PEC';
				$pa_code_name = 'billing_PEC';

			}

		}

		switch ( $field ) {
			case 'cf_name':
				return $cf_name;
				break;
			case 'pi_name':
				return $pi_name;
				break;
			case 'pec_name':
				return $pec_name;
				break;
			case 'pa_code_name':
				return $pa_code_name;
				break;
		}
	}


	/**
	 * Ottendo il nome delle colonne listino in base all'inclusione o meno dell'iva
	 *
	 * @param  int $n il numero del listino.
	 * @return string il nome del listino
	 */
	public static function get_prices_col_name( $n ) {

		$include_tax = get_option( 'woocommerce_prices_include_tax' );

		if ( 'yes' === $include_tax ) {

			return 'Listino ' . $n . ' (ivato)';

		} else {

			return 'Listino ' . $n;

		}

	}


	/**
	 * Ottengo gli attributi della singola variabile di prodotto
	 *
	 * @return string il contenuto del campo Note
	 */
	public static function get_product_notes() {

		$parent = wp_get_post_parent_id( get_the_ID() );

		if ( $parent ) {

			$parent_sku = get_post_meta( $parent, '_sku', true );
			$output = array(
				'parent_id' => $parent,
				'parent_sku' => $parent_sku,
			);

			$attributes = get_post_meta( $parent, '_product_attributes', true );

			$var_attributes = array();

			if ( is_array( $attributes ) ) {

				foreach ( $attributes as $key => $value ) {
					$meta = 'attribute_' . $key;
					$meta_val = get_post_meta( get_the_ID(), $meta, true );

					if ( $meta_val ) {
						$name = $value['name'];
						$var_attributes[ $name ] = $meta_val;
					}
				}

			}

			$output['var_attributes'] = $var_attributes;

			return json_encode( $output );

		} else {

			$args = array(
				'post_parent' => get_the_ID(),
				'post_type'   => 'product_variation',
				'post_status' => 'publish',
			);

			$output = array();

			if ( get_children( $args ) ) {

				$output['product_type'] = 'variable';

				$get_attributes = get_post_meta( get_the_ID(), '_product_attributes', true );

				if ( is_array( $get_attributes ) ) {

					$attributes = array();

					foreach ( $get_attributes as $key => $value ) {

						if ( isset( $value['is_taxonomy'] ) && 1 == $value['is_taxonomy']  ) {

							$terms = wp_get_object_terms( get_the_ID(), $key, array( 'fields' => 'slugs' ) );

						} elseif ( isset( $value['value'] ) && null != $value['value'] ) {
							
							$terms = explode( ' | ' , $value['value'] );

						}

						if ( ! is_wp_error( $terms ) ) {

							$attributes[ $key ] = $terms;

						}
						
					}

					$output['attributes'] = $attributes;
				}
				return json_encode( $output );

			}

		}
	}

}

