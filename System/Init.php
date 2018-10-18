<?

spl_autoload_register( "Autoloader" );

function Autoloader( $class ) {

    $file = ROOT_DIR . str_replace('\\', '/', str_replace( 'Evie\\Rest\\', '', $class ) ) . ".php";
    if ( is_readable( $file ) ) require_once( $file );

}