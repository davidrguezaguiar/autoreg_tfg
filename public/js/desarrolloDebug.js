(function () {
    $(function () {
        if ($('input[name^="Desarrollo"]').length > 2) {
            console.log('Se han encontrado [' + $('input[name^="Desarrollo"]').length + '] inputs de desarrollo: ');
        }
    });
    
    $('input[name^="Desarrollo"]').change(function(){
       
       var url = $('#urlDesarrolloAJAX').val();
       
        var valor = $(this).is(':checked');
        var nombre = $(this).attr('name');
        
       $.ajax({
            method: "POST",
            url: url,
            processData: true,
            data: {nombre , valor}
        }).done(function (response) {
            console.log(response); 
            $('#divError').addClass('show').html(response);

        }).fail(function (jqXHR, textStatus, errorThrown) {
            
        });
       
        
    });


})();


