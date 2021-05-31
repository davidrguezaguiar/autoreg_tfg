/**JS común del proyecto**/

/********************************* READY **************************************************************/
$(document).ready(function () {

    var url = window.location.href;
    //variable para guardar la opción del menú activa
    var menuSeleccionado = sessionStorage.getItem("menuActivo");
    //variable para guardar el texto del menú activo para las migas de pan
    var textoMenuSeleccionado = sessionStorage.getItem("textoMenuActivo");
    
    //para marcar la opción del menú seleccionado después de cargar la página
    if (menuSeleccionado) {
        //marcamos el menú activo
        $('#lista_' + menuSeleccionado).addClass('active');
        //Colocamos la página actual en las migas de pan
        $('#paginaActual').text(textoMenuSeleccionado); 

    } else {
        //borramos de la sesion la opciones seleccionadas del menú
        sessionStorage.removeItem("menuActivo");
        sessionStorage.removeItem("textoMenuActivo");
        
        //marcamos la primera opción del menú la primera vez y colocamos la miga de pan
        $('#lista_menu_1').addClass('active');
        $('#paginaActual').text($('#menu_1').text()); 

    }
    
    //si vamos a la simulación se borra de la sesión las opciones del menú activo
    if (url.indexOf('suplantaridentidad') > 0) {
        //borramos de la sesion la opciones seleccionadas del menú 
        sessionStorage.removeItem("menuActivo");
        sessionStorage.removeItem("textoMenuActivo");
    }
    
    //Si está visible enlace correo cabecera es que no estamos en la version móvil
    if ($('#enlace_correo').is(":visible")) {
        //Se oculta el menú móvil, se arregla margin-top y muestra versión normal del simular
        $('#menu_movil').hide();
        $('#contenido_interior').css("margin-top", "1%");
        $('#simular_movil').hide();
        $('#simular').show();  
    } else {
        //Se muestra el menú móvil, se arregla margin-top y se muestra versión móvil simular
        $('#menu_movil').show();
        $('#contenido_interior').css("margin-top", "8%");
        $('#simular_movil').show();
        $('#simular').hide();
    }

    /*Se añade el aria label a los input que crea el plugin chosen de select múltiple*/
    $('input.chosen-search-input').attr('aria-label', 'search');
    
});

/********************************FIN DEL READY**************************************************************/


(function () {
    
    //Para guardar la opción activa del menú, hay otra parte en el $(document).ready
    $('a[id^="menu_"]').click(function (event) {
        //paramos el evento del enlace
        event.preventDefault();
        //guardamos el id del menú seleccionado
        var menuActivo = $(this).attr("id");
        //texto menú actual
        var textoMenuActivo = $('#' + menuActivo).text(); 
     
        //Colocamos la página actual en las migas de pan
        $('#paginaActual').text(textoMenuActivo); 
        //se captura si el enlace va en la misma ventana o no
        var ventana = $(this).attr("target");
        //guardamos la ruta del enlace del menú seleccionado
        var urlMenu = $('#' + menuActivo).attr('href');

        //si hay menú activo y es en la misma venta
        if (menuActivo && ventana != '_blank') {
            //borramos la clase active de todas las opciones del menú
            $('li[id^="lista_menu_"]').removeClass('active');
            //y se la añadimos a la opción del menú seleccionado
            $('#lista_' + menuActivo).addClass('active');
            //se guarda en sesión la opción activa del menú y su texto
            sessionStorage.setItem('menuActivo', menuActivo);
            sessionStorage.setItem('textoMenuActivo', textoMenuActivo);
        }

        //se muestra según el target del enlace, ventana nueva o misma ventana
        if (ventana) {
            window.open(urlMenu);
        } else {
            //si no se muestra en la misma ventana
            window.open(urlMenu, '_self');
        }
    });
    
    //Si pulsamos en el enlace inicio de migas de pan o icono home del simular identidad
    $('a[id^="inicio_"]').click(function (event) {
        //Borramos de la sesión las opciones del menú
        sessionStorage.removeItem("menuActivo");
        sessionStorage.removeItem("textoMenuActivo");
    });
    
    //Si cambiamos el tamaño de la ventana del navegador
    $(window).resize(function () {
        //Si está visible enlace correo cabecera es que no estamos en la version móvil
        if ($('#enlace_correo').is(":visible")){
            //Se oculta el menú móvil, se arregla margin-top y muestra versión normal del simular
            $('#menu_movil').hide();
            $('#contenido_interior').css("margin-top", "1%");
            $('#simular_movil').hide();
            $('#simular').show();         
        }else{
            //Se muestra el menú móvil, se arregla margin-top y se muestra versión móvil simular
            $('#menu_movil').show();
            $('#contenido_interior').css("margin-top", "8%");
            $('#simular_movil').show();
            $('#simular').hide();
        }
        
    });

    
/***********Inicialización del DataTables ***********************/

    // DataTable genérico, el id de la tabla en el twig debe empezar por tabla_
    $("table[id^=tabla_").DataTable({
        language: {

            url: '/js/dataTables/Spanish.json'
        },
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        "drawCallback": function () {
            //Agregar unos pocos estilos del designsystem por clases
            $('[class^="dataTables_filter"]').addClass('ulpgcds-form__item');
            $('[class^="dataTables_length"]').addClass('ulpgcds-form__item--type-select');
            $('[class^="dataTables_info"]').addClass('ulpgcds-pager__results');
            $('[class^="dataTables_paginate"]').addClass('ulpgcds-pager__results');  
             /*popup opciones de menu en una tabla*/
            $('.opcionesMenu').popup({
                lastResort: 'left center',
                position: 'left center',
                hoverable: true,
                distanceAway: 1,
                delay: {
                    show: 300,
                    hide: 800
                },
                transition: 'slide down',
            });
        }
    });
/**********************************************************************************/

    // Se configura el calendario
    if ($( ".ulpgcds-datepicker" ).length){
        $( ".ulpgcds-datepicker" ).each(function(){
            $( this ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'dd/mm/yy',
                monthNamesShort: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
                    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
                closeText: 'Cerrar',
                prevText: '< Ant',
                nextText: 'Sig >',
                currentText: 'Hoy',
                dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Juv', 'Vie', 'Sáb'],
                dayNamesMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'],
                weekHeader: 'Sm',
                firstDay: 1
            });
        });
    }
        
})();

