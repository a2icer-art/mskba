const defaultTooltipOptions = {
    class: 'app-tooltip',
    showDelay: 200,
    hideDelay: 150,
    autoHide: true,
    escape: true,
    fitContent: true,
};

export const useTooltip = () => {
    const buildTooltip = (value, options = {}) => {
        const tooltipClass = [defaultTooltipOptions.class, options.class]
            .filter(Boolean)
            .join(' ');

        return {
            ...defaultTooltipOptions,
            ...options,
            value,
            class: tooltipClass,
        };
    };

    return {
        buildTooltip,
    };
};
