<?php
/**
 * Shortcuts to speed up the easy generation of dynamic HTML, extending the HTML utility class.
 */
class HTML_utils extends HTML {
	/**
	 * Generates an <a> element
	 * 
	 * @param string $href The link
	 * @param array  $atts An associative array of attributes (such as href)
	 * @param string $content Content to put between the opening and closing tags
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function a( $href = '#', $atts = array(), $content = '', $echo = false ) {
		$atts = parent::default_atts(
			array(
				'href' => $href,
			),
			$atts
		);
		$return = parent::gen_tag( 'a', $atts, $content );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates a <div> element
	 * 
	 * @param array  $atts
	 * @param string $content
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function div( $content = '', $atts = array(),  $echo = false ) {
		$return = parent::tag( 'div', $content, $atts );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates a <ul> element
	 * 
	 * @param string     $content
	 * @param array      $atts
	 * @param bool|false $echo
	 *
	 * @return string
	 */
	public static function ul( $content = '', $atts = array(), $echo = false ) {
		$return = parent::tag( 'ul', $content, $atts );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates an <li> element
	 * 
	 * @param string     $content
	 * @param array      $atts
	 * @param bool|false $echo
	 *
	 * @return string
	 */
	public static function li( $content = '', $atts = array(), $echo = false ) {
		$return = parent::tag( 'li', $content, $atts );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates an <img> element
	 * 
	 * @param string $src
	 * @param array  $atts
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function img( $src = '', $atts = array(), $echo = false ) {
		$atts = parent::default_atts(
			array(
				'src' => ( ! empty( $src ) ) ? $src : '',
				'alt' => '',
			),
			$atts
		);
		$return = parent::tag( 'img', false, $atts, true );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates an <input> element
	 * 
	 * @param string $name
	 * @param array  $atts
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function input( $name = '', $atts = array(), $echo = false ) {
		$atts = parent::default_atts(
			array(
				'id'   => ( ! empty( $name ) ) ? $name : parent::random_id(),
				'name' => ( ! empty( $name ) ) ? $name : parent::random_id(),
				'type' => 'text',
				//'tag'  => 'input'
			),
			$atts
		);
		$return = parent::tag('input',false,$atts);
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates an <input> element of type "text"
	 * 
	 * @param string $name
	 * @param array  $atts
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function input_text( $name = '', $atts = array(), $echo = false ) {
		return self::input( $name, $atts, $echo );
	}
	/**
	 * Generates an <input> element of type "checkbox"
	 *
	 * @param string $name
	 * @param array  $atts
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function input_checkbox( $name = '', $atts = array(), $echo = false ) {
		$atts = parent::default_atts(
			array(
				'id'   => ( ! empty( $name ) ) ? $name : parent::random_id(),
				'name' => ( ! empty( $name ) ) ? $name : parent::random_id(),
				'type' => 'checkbox',
				'tag'  =>'input'
			),
			$atts
		);
		$return = parent::gen_tag( $atts );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates an <input> element of type "radio"
	 *
	 * @param string $name
	 * @param array  $atts
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function input_radio( $name = '', $atts = array(), $echo = false ) {
		$atts = parent::default_atts(
			array(
				'id'   => ( ! empty( $name ) ) ? $name : parent::random_id(),
				'name' => ( ! empty( $name ) ) ? $name : parent::random_id(),
				'type' => 'radio',
				'tag'  =>'input'
			),
			$atts
		);
		$return = parent::gen_tag( $atts );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	/**
	 * Generates a <label> element
	 * 
	 * @param string $for
	 * @param array  $atts
	 * @param bool   $echo
	 *
	 * @return string
	 */
	public static function label( $for = '', $atts = array(), $echo = false ) {
		$atts = parent::default_atts(
			array(
				'for' => ( ! empty( $for ) ) ? $for : '',
				'tag'  =>'label'
			),
			$atts
		);
		$return = parent::gen_tag( $atts );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	public static function button( $content = '', $atts = array(), $echo = false ) {
		$atts = parent::default_atts(
			array(
				'type' =>'button',
			),
			$atts
		);
		$return = parent::tag( 'button', $content, $atts );
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}
	
}
