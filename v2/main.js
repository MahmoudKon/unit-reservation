$(function () {
  "use strict";

  var ajaxRequestInterval = 0;
  var setTime = 450;

  // Form
  var contactForm = function () {
    if ($("#contactForm").length > 0) {
      $("#contactForm").validate({
        rules: {
          project_number: {
            required: true,
          },
          unit_code: {
            required: true,
          },
          authentication_code: {
            required: true,
            minlength: 5,
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

  function runAjaxRequest(form) {
    if (localStorage.getItem('login-time') != login_time) {
			window.location.href = 'index.php';
		}

    $.ajax({
      type: "POST",
      url: "send-request.php",
      data: $(form).serialize(),
      sync: true,
      beforeSend: function () {$(".submitting").css("display", "block").text("Submitting...")},
      success: function (msg) {
          if (msg === 'الوحدة المحددة غير متاحة للحجز.' || msg === 'تم حجز الوحدة من قبل') {
              $(".submitting").css("display", "block").text("Resending In Moments...");
               reSetInterval(form, setTime);
          } else if (msg === 'تم حجز الوحدة بنجاح.') {
              $(".submitting").css("display", "block").text("Resending In Moments...");
              reSetInterval(form, setTime);

          } else if (msg == 'كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)') {
              //Clear Ajax Request Interval
              clearInterval(ajaxRequestInterval);
              $(".submitting").css("display", "none");
              
          } else if (msg == 'goto login') {
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

          console.log(msg);
          if (msg == 'كود الوحدة غير موجود في المشروع') msg = '';
          if (msg !== '')  showMessage(msg);
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

  function reSetInterval(form, time = 650) {
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
});
