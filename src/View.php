<?php
/**
 * A very simple templating class
 */

namespace CheckoutFinland\WooCommercePaymentGateway;

/**
 * View
 */
class View {

    /**
     * The template to render.
     *
     * @var string
     */
    protected $template;

    /**
     * The data to render the view with.
     *
     * @var array
     */
    protected $data;

    /**
     * Constructor
     *
     * @param string $template The template to render.
     */
    public function __construct( string $template ) {
        $this->template = $this->get_template_path( $template );
    }

    /**
     * Render the wanted template with given data.
     *
     * @param mixed $data The data to render the view with.
     * @return void
     */
    public function render( $data = null ) { // @codingStandardsIgnoreLine
        require $this->template;
    }

    /**
     * Get a complate template path by a template name.
     *
     * @param string $template Template name to work with.
     * @return string The complete path.
     *
     * @throws \Exception An exception if the template file given was not found.
     */
    protected function get_template_path( string $template ) : string {
        $plugin_instance = Plugin::instance();

        $plugin_dir = $plugin_instance->get_plugin_dir();

        // Check the existence of the template.
        if ( file_exists( $plugin_dir . '/src/View/' . $template . '.php' ) ) {
            return $plugin_dir . '/src/View/' . $template . '.php';
        }
        else {
            throw new \Exception( 'Template "' . $template . '" (' . $plugin_dir . '/src/View/' . $template . '.php' . ') could not be found.' );
        }
    }
}
