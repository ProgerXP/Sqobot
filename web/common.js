// initialize Underscore.string.
_.mixin(_.str.exports())

$ && $(function () {
  hookTasks()
})

function hookTasks() {
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
        return str.replace(/\d+$/, inputs.length + 1)
      })
      .appendTo(this)
  })

  $(document).on('change', '.web-tasks select', function () {
    $(this).parents('form:first').find('.btn input:first')[0].focus()
  })

  $(document).on('click', '.web-tasks input[type=submit]', function () {
    var btn = $(this)
    var form = btn.parents('form:first')
    var root = btn.parents('.web-tasks:first')

    root.children('table').addClass('pivot')
    var url = form.attr('action') + '?naked=1&output=1&' + btn.attr('name') + '=1'

    root.find('td.output')
      .empty()
      .append('<p class=loading>Loading...</p>')
      .load(url, form.serialize())

    return false
  })
}
