$(function () {
  "use strict";

  var ajaxRequestInterval = 0;
  var setTime = 200;

  // Form
  var contactForm = function () {
    if ($("#contactForm").length > 0) {
      $("#contactForm").validate({
        rules: {
          project_number: {
            required: true,
          },
          unit_code: {
            required: false,
          },
          authentication_code: {
            required: true
          },
        },
        messages: {
          unit_code: "Please enter project number",
          unit_code: "Please enter unit code",
          authentication_code: "Please enter a authentication code",
        },

        /* submit via ajax */
          submitHandler: function (form) {
            ajaxRequest(form);
          },
      });
    }
  };

  var ajaxRequest = function (form) {
    // runAjaxRequest(form);
    // reSetInterval(form);

      new Promise((resolve, reject) => {
          runAjaxRequest(form);
      }).then(function() {
        reSetInterval(form);
      });
  };

  function hiddenTextAreat(token)
  {
    $('body').find('textarea').each(function() {
        if ($(this).val() == token) {
            $(this).prop('readonly', true).prop('disabled', true);
        }
    })
  }

  function runAjaxRequest(form) {
    if (localStorage.getItem('login-time') != login_time) {
			window.location.href = 'index.php';
		}

    $.ajax({
      type: "POST",
      url: "php/send-request.php",
      data: $(form).serialize(),
      sync: true,
      beforeSend: function () {$(".submitting").css("display", "block").text("Submitting...")},
      success: function (response) {
          response = JSON.parse(response);

          // hiddenTextAreat(response.token);
          if (response.message === 'الوحدة المحددة غير متاحة للحجز.' || response.message === 'تم حجز الوحدة من قبل') {
             // $(".submitting").css("display", "block").text("Resending In Moments...");
              reSetInterval(form, setTime);

          } else if (response.message === 'تم حجز الوحدة بنجاح.') {
             // $(".submitting").css("display", "block").text("Resending In Moments...");
              reSetInterval(form, setTime);

          } else if (response.message == 'كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)') {
              //Clear Ajax Request Interval
              clearInterval(ajaxRequestInterval);
              $(".submitting").css("display", "none");
              
          } else if (response.message == 'goto login') {
            window.location.href = 'index.php';

          } else {
              reSetInterval(form);
              // //Show Success Message
              // setTimeout(function () {
              //   $("#contactForm").fadeOut();
              // }, 1000);

              // setTimeout(function () {
              //   $("#form-message-success").html(msg);
              //   $("#form-message-success").fadeIn();
              // }, 1400);
          }
          
          if (response.message !== '' && ! response.message.includes('Fatal error'))  showMessage(response.message);
          console.log(response.message);
          console.log(`Request Will Send After : ${setTime}`);
      },
      error: function () {
          $("#form-message-warning").html("Something went wrong. Please try again.");
          showMessage('Something went wrong. Please try again.', 'color: #f00');
          $("#form-message-warning").fadeIn();
          $(".submitting").css("display", "none");
      },
    });
  }

  function reSetInterval(form, time = 200) {
      setTime = time;
      
      async function waitUntil(form, time) {
        return await new Promise(resolve => {
          const ajaxRequestInterval = setInterval(() => {
                runAjaxRequest(form);
                clearInterval(ajaxRequestInterval);
          }, time);
        });
      }

      waitUntil(form, time);
  }

  function showMessage(msg, style_class = '') {
      $('#load-messages').prepend(`<li style="padding: 10px 0; border-bottom: 1px solid #ddd; ${style_class}">${msg}</li>`);
  }

  contactForm();


  $('#add-new-key').on('click', function() {
      let parent = $(this).parent();

      if (parent.find('.clone-row:last textarea').val() == '') {
          alert("يرجي ملئ جميع الحقول");
          return ;
      }

      parent.find('.clone-row:first').clone().appendTo( parent );
      parent.find('.clone-row:last textarea').val('').prop('readonly', false).prop('disabled', false);
  });

  $('body').on('click', '.clone-row .remove-clone', function() {
      if ( $('body').find('.clone-row').length == 1 ) {
          alert("لا يمكن حذف جميع الحقول");
          return ;
      }
      $(this).parent().remove();
  });

  $('body').on('change', '.clone-row textarea', function() {
      let ele = $(this);

      $("body").find(".clone-row textarea").not($(this)).each(function() {
          if (ele.val() == $(this).val()) {
            ele.val('');
            alert("هذا التوكن مستخدم مسبقا");
          }
      });
  });
});
