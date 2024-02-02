$(function () {
  "use strict";

  var ajaxRequestInterval = 0;
  var setTime = 1800000;

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
            runAjaxRequest(form);
          },
      });
    }
  };

  function runAjaxRequest(form) {
    $.ajax({
      type: "POST",
      url: "send-request.php",
      data: $(form).serialize(),
      sync: true,
      beforeSend: function () {
        $(".submitting").css("display", "block").text("Submitting...");
        $('input[type="submit"]').attr('disabled', 'disabled');
      },
      success: function (msg) {
          if (msg === 'الوحدة المحددة غير متاحة للحجز.' || msg === 'تم حجز الوحدة من قبل') {
              $(".submitting").css("display", "block").text("Resending In Moments...");

          } else if (msg === 'تم حجز الوحدة بنجاح.') {
              $(".submitting").css("display", "block").text("Resending In Moments...");
              reSetInterval(form, setTime);

          } else if (msg == 'كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)') {
              //Clear Ajax Request Interval
              clearInterval(ajaxRequestInterval);
              $(".submitting").css("display", "none");
              
          }
          
          if (msg !== '')  showMessage(msg);
          console.log(msg);
          console.log(`Request Will Send After : ${setTime}`);
      },
      error: function () {
          $("#form-message-warning").html("Something went wrong. Please try again.");
          showMessage('Something went wrong. Please try again.', 'color: #f00');
          $("#form-message-warning").fadeIn();
          $(".submitting").css("display", "none");
      },
      complete: function() {
        $(".submitting").css("display", "none");
        $('input[type="submit"]').removeAttr('disabled');
      }
    });
  }

  function reSetInterval(form, time = 1800000) {
      setTime = time;
      clearInterval(ajaxRequestInterval);
      ajaxRequestInterval = setInterval(function () { runAjaxRequest(form) }, time);
  }

  function showMessage(msg, style_class = '') {
      $('#load-messages').prepend(`<li style="padding: 10px 0; border-bottom: 1px solid #ddd; ${style_class}">${msg}</li>`);
  }

  contactForm();

  $('#reset-form').on('click', function(e) {
      e.preventDefault();
      document.getElementById("contactForm").reset();
  });
});
