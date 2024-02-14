$(function () {
  "use strict";

  var ajaxRequestInterval = 0;
  var setTime = 200;
  var sleepTime = 400;
  var abort_request_time = 40000; // 40 S
  var projects = [];
  let current_project_index = 0;

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

    if ( !projects[current_project_index] ) {
      current_project_index = 0;
    }

    $.ajax({
      type: "POST",
      url: "send-request.php",
      data: $(form).serialize()+`&project=${projects[current_project_index]}`,
      sync: true,
      timeout: abort_request_time,
      beforeSend: function () {$(".submitting").css("display", "block").text("Submitting...")},
      success: function (response) {
        console.log("old => " + current_project_index);
        current_project_index++;
        console.log("new => " + current_project_index);
        console.log(response);
        response = JSON.parse(response);
        let ele = "load-messages";
        let elements_count = $('body').find('[name="authentication_code[]"]').length;
        let clearInterval = false;

        response.forEach(function(row) {
            let message = row.details.message || row.details || '';
            if (message === 'الوحدة المحددة غير متاحة للحجز.' || message === 'تم حجز الوحدة من قبل') {
                $(".submitting").css("display", "block").text("Resending In Moments...");
            } else if (message === 'تم حجز الوحدة بنجاح.') {
                $(".submitting").css("display", "block").text("Resending In Moments...");

            } else if (message == 'كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)') {
              console.log('in');
                //Clear Ajax Request Interval
                if (elements_count < 2) clearInterval = true;
                $(".submitting").css("display", "none");
                
            } else if (message == 'goto login') {
              window.location.href = 'index.php';
            } else {
                if (row.details.status == 200) ele = "load-success";
            }
            
            if (message !== '' && ! message.includes('Fatal error'))  showMessage(message, ele);
            console.log(message);
        });

        if (clearInterval) {
          clearInterval(ajaxRequestInterval);
        } else {
          if (elements_count > 1) reSetInterval(form);
          else reSetInterval(form, setTime);
        }
        
        console.log(`Request Will Send After : ${setTime}`);
      },
      error: function (response) {
          console.log(response);
          reSetInterval(form);
          // $("#form-message-warning").html("Something went wrong. Please try again.");
          // showMessage('Something went wrong. Please try again.', 'color: #f00');
          // $("#form-message-warning").fadeIn();
          // $(".submitting").css("display", "none");
      },
    });
  }

  function reSetInterval(form, time = 200) {
      setTime = time;

      setTimeout(() => {
          async function waitUntil(form, time) {
              return await new Promise(resolve => {
                  const ajaxRequestInterval = setInterval(() => {
                        runAjaxRequest(form);
                        clearInterval(ajaxRequestInterval);
                  }, time);
              });
          }
          waitUntil(form, time);
      }, sleepTime);
      
  }

  function showMessage(msg, ele = "load-messages") {
      $(`#${ele}`).prepend(`<li style="padding: 10px 0; border-bottom: 1px solid #ddd; ">${msg}</li>`);
  }

  contactForm();

  $('.add-new-key').on('click', function() {
      let parent = $(this).parent().find('.clone-body');

      if (parent.find('.clone-row:last .clone-input').val() == '') {
          alert("يرجي ملئ جميع الحقول");
          return ;
      }

      parent.find('.clone-row:first').clone().appendTo( parent );
      parent.find('.clone-row:last .clone-input').val('').prop('readonly', false).prop('disabled', false);
  });

  $('body').on('click', '.clone-row .remove-clone', function() {
      if ( $(this).closest('.clone-body').find('.clone-row').length == 1 ) {
          alert("لا يمكن حذف جميع الحقول");
          return ;
      }

      syncProjects( $(this).parent().find('.clone-input').val() );
      $(this).parent().remove();
  });

  $('body').on('change', '.clone-row .clone-input', function() {
      let ele = $(this);

      $("body").find(".clone-row .clone-input").not($(this)).each(function() {
          if (ele.val() == $(this).val()) {
            ele.val('');
            alert("هذه القيمة مستخدمة مسبقا");
          }
      });
  });

  $('body').on('change', '.project_number', function() { syncProjects(); });

  function syncProjects(remove = null) {
      if (remove) {
        var index = projects.indexOf( remove );
        if (index !== -1) {
          projects.splice(index, 1);
        }
      } else {
        $.each($('body').find(".project_number"), function(key, val) {
          if ($(this).val()) {
            projects[key] = $(this).val();
          }
        });
      }
  }
  syncProjects();
});
