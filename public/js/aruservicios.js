(function () {

    /** Se comprueba la URL y se lanzan las funciones necesarias */
    $(document).ready(function () {

        if (document.getElementById('formDatosUsuario1')) {
            $('#refrescarImagen').click();
        }
        if (document.getElementById('formDatosUsuario2')) {
            comprobarDocumentoIdentificativo();
        }

    });

    $('#documentoIdentificativo').blur(function () {
        comprobarDocumentoIdentificativo();
    });

    $("#ULPGCPassWord_ulpgcpassword_first").keyup(function () {
        comprobarPassword1();
    }).focus(function () {
        $('#pswd_info').show();
    }).blur(function () {
        $('#pswd_info').hide();
    });

    $("#ULPGCPassWord_ulpgcpassword_second").keyup(function () {
        comprobarPassword2();
    }).focus(function () {
        $('#pswd_repeat').hide();
    }).blur(function () {
        $('#pswd_repeat').hide();
    });
    

    comprobarDocumentoIdentificativo = function () {
        
        var url = $('#urlidentificacion').val();
        var token = $('#token').val();
        var identidad = $('#documentoIdentificativo').val();
        
        $('#mensajesError').html('');
        document.getElementById('mensajesError').style.display = "none";        

        $('#fieldCorreoElectronicoBD').addClass('hidden');
        $('#camposCorreoElectronico').removeClass('hidden');

        $.ajax({
            method: "POST",
            url: url,
            processData: true,
            data: {token, identidad}
        }).done(function (response) {
            
            if (document.getElementById('formDatosUsuario1')) {
                document.getElementById('botonSubmit').disabled = false;
            }

            $('#fieldCorreoElectronicoBD > .clone').each(function () {
                $(this).remove();
            });

            if ($.parseJSON(response).listadoCorreos.length > 0) {
                listaCorreos = true;
                $('#camposCorreoElectronico').addClass('hidden');
                $('#fieldCorreoElectronicoBD').removeClass('hidden');

                $.each($.parseJSON(response).listadoCorreos, function (index, arrayCorreo) {

                    $('#plantillaCorreo').clone().appendTo('#fieldCorreoElectronicoBD')
                            .attr('id', index)
                            .addClass('clone')
                            .removeClass('hidden');

                    $('#' + index + ' input').attr('id', 'correoElectronicoBD' + index).val(index);
                    $('#' + index + ' input').attr('name', 'correoElectronicoBD');
                    $('#' + index + ' label').attr('for', 'correoElectronicoBD' + index).html(arrayCorreo.amaila_ofuscado);
                    
                });
                $('#correoElectronicoUsuario').val('user@isp.com');
                        $('#correoElectronicoUsuario2').val('user@isp.com');
            } else {
                $('#fieldCorreoElectronicoBD').addClass('hidden');
                $('#camposCorreoElectronico').removeClass('hidden');
            }

        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#spinnerEnviando').removeClass('active');
            if (document.getElementById('formDatosUsuario1')) {
                document.getElementById('botonSubmit').disabled = true;
            }
            $('#mensajesError').show();
            $('#mensajesError').html(jqXHR.responseText);
        });

    };


    ValidarPaso1 = function () {

        var url = $('#urlcomprobarpaso1').val();
        var token = $('#token').val();

        var identidad = $('#documentoIdentificativo').val();
        var soporteID = $('#inputValidacionSoporteDocumento').val();
        var fechaID = $('#inputValidacionFechaDocumento').val();
        var imagenRobot = $('#imagenRobot').val();
        var manifiestoOposicion = $('input[name=consultaDocumentoIdentificativo]:checked').val();
        
        $('#mensajesError').html('');
        document.getElementById('mensajesError').style.display = "none";        

        $.ajax({
            method: "POST",
            url: url,
            processData: true,
            data: {token, identidad, soporteID, fechaID, imagenRobot, manifiestoOposicion}
        }).done(function (response) {

            var respuesta = $.parseJSON(response);
            document.getElementById("formDatosUsuario1").submit();

        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#spinnerEnviando').removeClass('active');            
            $('#refrescarImagen').click();
            $('#mensajesError').show();
            $('#mensajesError').html(jqXHR.responseText);
        });
    };


    ValidarPaso2 = function (event) {

        var url = $('#urlcomprobarpaso2').val();
        var token = $('#token').val();

        var telefonoMovil = $('#telefonoMovil').val();
        var correoElectronico = false;
        var correoElectronico2 = $('#correoElectronicoUsuario2').val();

        $('#mensajesError').html('');
        document.getElementById('mensajesError').style.display = "none";        

        if (isNaN($('input[name=correoElectronicoBD]:checked').val())) {

            if ($('input[name=correoElectronicoUsuario]').val().length > 0) {
                correoElectronico = $('input[name=correoElectronicoUsuario]').val();
            }

        } else {
            correoElectronico = $('input[name=correoElectronicoBD]:checked').val();
            $('#correoElectronicoUsuario').val(correoElectronico); 
            $('#correoElectronicoUsuario2').val(correoElectronico); 
        }

        if (correoElectronico === false) {
            return false;
        }

        if (telefonoMovil && correoElectronico !== false) {
            $.ajax({
                method: "POST",
                url: url,
                processData: true,
                data: {token, telefonoMovil, correoElectronico, correoElectronico2}
            }).done(function (response) {

                var respuesta = $.parseJSON(response);
                solicitarCodigoVerificacion();

            }).fail(function (jqXHR, textStatus, errorThrown) {
                $('#spinnerEnviando').removeClass('active');                
                $('#mensajesError').show();
                $('#mensajesError').html(jqXHR.responseText);
             
            });
        }
    };


    ValidarPaso3 = function () {

        var url = $('#urlcomprobarpaso3').val();
        var token = $('#token').val();

        var codigoVerificacion = $('#codigoVerificacion').val();
        var password1 = $('#ULPGCPassWord_ulpgcpassword_first').val();
        var password2 = $('#ULPGCPassWord_ulpgcpassword_second').val();

        $('#mensajesError').html('');
        document.getElementById('mensajesError').style.display = "none";

        $.ajax({
            method: "POST",
            url: url,
            processData: true,
            data: {token, codigoVerificacion, password1, password2}
        }).done(function (response) {
            var respuesta = $.parseJSON(response);
            validarCodigoverificacion();

        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#spinnerEnviando').removeClass('active');            
            $('#mensajesError').show();
            $('#mensajesError').html(jqXHR.responseText);
        });

    };


    solicitarCodigoVerificacion = function () {

        var url = $('#urlcodigoverificacion').val();
        var token = $('#token').val();

        var telefonoMovil = $('#telefonoMovil').val();
        var correoElectronico = false;
        var correoElectronico2 = $('#correoElectronicoUsuario2').val();

        $('#mensajesError').html('');
        document.getElementById('mensajesError').style.display = "none";

        if (isNaN($('input[name=correoElectronicoBD]:checked').val())) {

            if ($('input[name=correoElectronicoUsuario]').val().length > 0) {
                correoElectronico = $('input[name=correoElectronicoUsuario]').val();
            }

        } else {
            correoElectronico = $('input[name=correoElectronicoBD]:checked').val();
        }

        if (correoElectronico === false) {
            return false;
        }

        if (correoElectronico !== false) {

            $.ajax({
                method: "POST",
                url: url,
                processData: true,
                data: {token, telefonoMovil, correoElectronico, correoElectronico2}
            }).done(function (response) {
                var respuesta = $.parseJSON(response);
                if (respuesta.correoEnviado) {
                    document.getElementById("formDatosUsuario2").submit();
                }

            }).fail(function (jqXHR, textStatus, errorThrown) {
                $('#spinnerEnviando').removeClass('active');                
                $('#mensajesError').show();
                $('#mensajesError').html(jqXHR.responseText);
            });
        }
    };


    validarCodigoverificacion = function () {

        var url = $('#urlcomprobarverificacion').val();
        var token = $('#token').val();
        var codigoVerificacion = $('#codigoVerificacion').val();

        $.ajax({
            method: "POST",
            url: url,
            processData: true,
            data: {token, codigoVerificacion}
        }).done(function (response) {
            var respuesta = $.parseJSON(response);
            document.getElementById("formDatosUsuario3").submit();

        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#spinnerEnviando').removeClass('active');            
            $('#mensajesError').show();
            $('#mensajesError').html(jqXHR.responseText);
        });
    };


    $('#refrescarImagen').click(function () {
        var url = $('#urlcodigo').val();
        var token = $('#token').val();

        $.ajax({
            method: "POST",
            url: url,
            processData: true,
            data: {token}
        }).done(function (response) {
            var respuesta = $.parseJSON(response);

            if (respuesta.imagenGenerada) {
                $('#imagenCaptcha').attr('src', respuesta.captcha);
            } else {
                $('#imagenCaptcha').attr('src', '');
                $('#mensajesError').html('Error al generar el código Captcha.');
            }

        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#mensajesError').show();
            $('#mensajesError').html(jqXHR.responseText);
        });
    });


    comprobarPassword1 = function () {
        
        var pswd = $('#ULPGCPassWord_ulpgcpassword_first').val();

        if (pswd.length < 8) {
            $('#length1').removeClass('valid').addClass('invalid');
        } else {
            $('#length1').removeClass('invalid').addClass('valid');
        }

        if (pswd.length < 16 && pswd.length > 1) {
            $('#length2').removeClass('invalid').addClass('valid');
        } else {
            $('#length2').removeClass('valid').addClass('invalid');
        }

        if (pswd.match(/[a-z]/)) {
            $('#letter').removeClass('invalid').addClass('valid');
        } else {
            $('#letter').removeClass('valid').addClass('invalid');
        }

        if (pswd.match(/[A-Z]/)) {
            $('#capital').removeClass('invalid').addClass('valid');
        } else {
            $('#capital').removeClass('valid').addClass('invalid');
        }

        if (pswd.match(/\d/)) {
            $('#number').removeClass('invalid').addClass('valid');
        } else {
            $('#number').removeClass('valid').addClass('invalid');
        }

        if (pswd.match(/[_\-*\/:;.,]/)) {
            $('#symbol').removeClass('invalid').addClass('valid');
        } else {
            $('#symbol').removeClass('valid').addClass('invalid');
        }

        if (pswd.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_\-*\/:;.,])[A-Za-z\d_\-*\/:;.,]{8,15}$/)) {
            $('#format1').removeClass('invalid_pwd').addClass('valid_pwd');
        } else {
            $('#format1').removeClass('valid_pwd').addClass('invalid_pwd');
        }

        if (pswd.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_\-*\/:;.,])[A-Za-z\d_\-*\/:;.,]{8,15}$/)) {
            $('#ULPGCPassWord_ulpgcpassword_first').removeClass('invalid_pwd').addClass('valid_pwd');
        } else {
            $('#ULPGCPassWord_ulpgcpassword_first').removeClass('valid_pwd').addClass('invalid_pwd');
        }

        if (pswd.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[_\-*\/:;.,])[A-Za-z\d_\-*\/:;.,]{8,15}$/))
        {
            $('#pswd_info').hide();
            return true;
        } else {
            $('#pswd_info').show();
        }
        
        return false;

    };


    comprobarPassword2 = function () {

        var pswd2 = $('#ULPGCPassWord_ulpgcpassword_second').val();

        if ($('#ULPGCPassWord_ulpgcpassword_first').val() == $('#ULPGCPassWord_ulpgcpassword_second').val()) {
            $('#repeat').removeClass('invalid').addClass('valid');

        } else {
            $('#repeat').removeClass('valid').addClass('invalid');            
        }

        if (pswd2.length < 8) {
            $('#length1B').removeClass('valid').addClass('invalid');
        } else {
            $('#length1B').removeClass('invalid').addClass('valid');            
        }

        if (pswd2.length > 15) {
            $('#length2B').removeClass('valid').addClass('invalid');
        } else {
            $('#length2B').removeClass('invalid').addClass('valid');
        }

        if (pswd2.match(/[a-z]/)) {
            $('#letterB').removeClass('invalid').addClass('valid');
        } else {
            $('#letterB').removeClass('valid').addClass('invalid');
        }

        if (pswd2.match(/[A-Z]/)) {
            $('#capitalB').removeClass('invalid').addClass('valid');
        } else {
            $('#capitalB').removeClass('valid').addClass('invalid');
        }

        if (pswd2.match(/\d/)) {
            $('#numberB').removeClass('invalid').addClass('valid');
        } else {
            $('#numberB').removeClass('valid').addClass('invalid');
        }

        if (pswd2.match(/[-_*:,;.@]/)) {
            $('#symbolB').removeClass('invalid').addClass('valid');
        } else {
            $('#symbolB').removeClass('valid').addClass('invalid');
        }

        if ($('#ULPGCPassWord_ulpgcpassword_first').val() == $('#ULPGCPassWord_ulpgcpassword_second').val()) {
            $('#ULPGCPassWord_ulpgcpassword_second').removeClass('invalid_pwd').addClass('valid_pwd');
            $('#pswd_repeat').hide();
            return true;
        } else {
            $('#ULPGCPassWord_ulpgcpassword_second').removeClass('valid_pwd').addClass('invalid_pwd');
            $('#pswd_repeat').hide();
        }

        return false;

    };
    

    comprobarContraseñas = function () {
        
        if (comprobarPassword2() && comprobarPassword1()) {
            $('#mensajesError').html('');
            document.getElementById('mensajesError').style.display = "none";              
            ValidarPaso3();
            
        } else {
            $('#spinnerEnviando').removeClass('active');            
            $('#mensajesError').show();
            $('#mensajesError').html('La contraseña de verificaci\xf3n no coincide con la contraseña introducida.');            
        }
    };


})();