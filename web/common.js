// initialize Underscore.string.
_.mixin(_.str.exports())

$ && $(function () {
  $.each(taskHookers, function (name, func) {
    var roots = $('.web-' + name).each(function (i) { func($(this), i) })
  })
})

var taskHookers = {
  tasks: function (container, i) {
    if (i == 0) {
      var timer

      $(document).on('focusin focusout', '.web-tasks td.args', function (e) {
        var inputs = $('input', this)

        var empty = inputs.filter(function (i) {
          return $.trim($(this).val()) == ''
        })

        var addEmpty = false

        if (e.type == 'focusin') {
          clearTimeout(timer)
          addEmpty = e.target == empty.last()[0] || e.target == inputs.last()[0]
        } else if (inputs.length > 3 && empty.last()[0] == inputs.last()[0]) {
          timer = setTimeout(function () { empty.last().remove() }, 50)
        }

        addEmpty && inputs
          .first().clone().val('')
          .attr('placeholder', function (i, str) {
            return str.replace(/\d+$/, i + 1)
          })
          .appendTo(this)
      })
    }
  },
}
